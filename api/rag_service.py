from typing import List, Dict, Any, Optional
from bedrock_client import BedrockClient
from models import RAGResponse, RAGMode, RetrievalResult, DocumentSource
from config import config
import logging
import asyncio
from concurrent.futures import ThreadPoolExecutor
import uuid

logger = logging.getLogger(__name__)

# Default system prompt injected for every chat question
DEFAULT_SYSTEM_PROMPT = """You are an AI assistant for Lukla Operations Engine, a manufacturing operations platform for garment production. 
You have access to the system's business requirements, process flows, and operational documentation.

When answering:
- Be specific and reference actual details from the provided context when available
- If the context contains relevant information, use it directly and cite it
- If the question is about Lukla Operations (orders, inventory, QC, payroll, samples, clients), always check the context first
- Answer in the same language as the question (Indonesian or English)
- Be concise but complete"""


class RAGService:
    """Service layer for RAG operations using local document retrieval + Bedrock."""

    def __init__(self):
        self.bedrock_client = BedrockClient()
        self.executor = ThreadPoolExecutor(max_workers=4)

    async def process_query(
        self,
        query: str,
        mode: RAGMode,
        knowledge_base_id: Optional[str] = None,
        system_prompt: Optional[str] = None,
        max_tokens: Optional[int] = None,
        temperature: Optional[float] = None,
        top_k: Optional[int] = None,
    ) -> RAGResponse:
        session_id = str(uuid.uuid4())
        original_values = self._apply_config_overrides(max_tokens, temperature, top_k)

        try:
            if mode == RAGMode.RETRIEVE_ONLY:
                return await self._retrieve_only(query, knowledge_base_id, session_id)
            elif mode == RAGMode.GENERATE_ONLY:
                return await self._generate_with_local_docs(query, system_prompt, session_id)
            elif mode == RAGMode.RETRIEVE_AND_GENERATE:
                return await self._retrieve_and_generate(query, knowledge_base_id, session_id)
            elif mode == RAGMode.CUSTOM_RAG:
                return await self._custom_rag(query, knowledge_base_id, system_prompt, session_id)
            else:
                raise ValueError(f"Unsupported RAG mode: {mode}")
        finally:
            self._restore_config_values(original_values)

    # ------------------------------------------------------------------
    # Core modes
    # ------------------------------------------------------------------

    async def _generate_with_local_docs(
        self, query: str, system_prompt: Optional[str], session_id: str
    ) -> RAGResponse:
        """
        Retrieve relevant chunks from local S3 documents, then generate
        a grounded answer using Bedrock.  Falls back to pure generation
        when no chunks match.
        """
        loop = asyncio.get_event_loop()

        # Retrieve locally
        chunks = await loop.run_in_executor(
            self.executor,
            self.bedrock_client.retrieve_relevant_chunks,
            query,
            config.TOP_K,
        )

        sys_prompt = system_prompt or DEFAULT_SYSTEM_PROMPT
        sources: List[RetrievalResult] = []

        if chunks:
            # Build context block from top chunks
            context_parts = []
            for i, chunk in enumerate(chunks, 1):
                context_parts.append(f"[Source {i} — {chunk['source']}]\n{chunk['content']}")
                sources.append(RetrievalResult(
                    content=chunk['content'],
                    metadata={'source': chunk['source'], 'chunk_index': chunk['chunk_index']},
                    source=DocumentSource(
                        source_id=chunk['source'],
                        uri=f"s3://{config.AWS_BUCKET}/{chunk['source']}",
                        score=chunk['score'],
                    ),
                ))

            context_block = '\n\n---\n\n'.join(context_parts)
            prompt = (
                f"Use the following context from the Lukla Operations documentation to answer the question.\n\n"
                f"=== CONTEXT ===\n{context_block}\n=== END CONTEXT ===\n\n"
                f"Question: {query}"
            )
            logger.info(f"Using {len(chunks)} document chunks for query")
        else:
            # No matching chunks — ask model from general knowledge
            prompt = query
            logger.info("No matching document chunks; using model general knowledge")

        try:
            answer = await loop.run_in_executor(
                self.executor,
                self.bedrock_client.invoke_model,
                prompt,
                sys_prompt,
            )
        except Exception as e:
            logger.error(f"Model invocation failed: {e}")
            raise Exception(f"Response generation failed: {e}")

        return RAGResponse(
            answer=answer,
            sources=sources,
            mode=RAGMode.GENERATE_ONLY,
            model_id=config.BEDROCK_MODEL_ID,
            session_id=session_id,
        )

    async def _retrieve_only(
        self, query: str, kb_id: Optional[str], session_id: str
    ) -> RAGResponse:
        loop = asyncio.get_event_loop()

        # Try Bedrock KB first, fall back to local
        if config.KNOWLEDGE_BASE_ID or kb_id:
            try:
                kb_response = await loop.run_in_executor(
                    self.executor, self.bedrock_client.query_knowledge_base, query, kb_id
                )
                sources = self._parse_retrieval_results(kb_response)
                summary = self._create_content_summary(sources)
                return RAGResponse(
                    answer=f"Retrieved {len(sources)} relevant documents:\n\n{summary}",
                    sources=sources,
                    mode=RAGMode.RETRIEVE_ONLY,
                    model_id=config.BEDROCK_MODEL_ID,
                    session_id=session_id,
                )
            except Exception as e:
                logger.warning(f"KB retrieve failed, falling back to local: {e}")

        # Local retrieval
        chunks = await loop.run_in_executor(
            self.executor, self.bedrock_client.retrieve_relevant_chunks, query, config.TOP_K
        )
        sources = [
            RetrievalResult(
                content=c['content'],
                metadata={'source': c['source']},
                source=DocumentSource(source_id=c['source'], uri=f"s3://{config.AWS_BUCKET}/{c['source']}", score=c['score']),
            )
            for c in chunks
        ]
        summary = self._create_content_summary(sources)
        return RAGResponse(
            answer=f"Retrieved {len(sources)} relevant document chunks:\n\n{summary}",
            sources=sources,
            mode=RAGMode.RETRIEVE_ONLY,
            model_id=config.BEDROCK_MODEL_ID,
            session_id=session_id,
        )

    async def _retrieve_and_generate(
        self, query: str, kb_id: Optional[str], session_id: str
    ) -> RAGResponse:
        """Prefer Bedrock KB; fall back to local document RAG."""
        kb_id = kb_id or config.KNOWLEDGE_BASE_ID
        if kb_id:
            loop = asyncio.get_event_loop()
            try:
                response = await loop.run_in_executor(
                    self.executor, self.bedrock_client.retrieve_and_generate, query, kb_id
                )
                answer = response['output']['text']
                sources = self._parse_citations(response.get('citations', []))
                return RAGResponse(
                    answer=answer, sources=sources,
                    mode=RAGMode.RETRIEVE_AND_GENERATE,
                    model_id=config.BEDROCK_MODEL_ID,
                    session_id=session_id,
                    usage=response.get('usage'),
                )
            except Exception as e:
                logger.warning(f"KB retrieve_and_generate failed, falling back to local: {e}")

        # Fall back to local document RAG
        return await self._generate_with_local_docs(query, None, session_id)

    async def _custom_rag(
        self, query: str, kb_id: Optional[str], system_prompt: Optional[str], session_id: str
    ) -> RAGResponse:
        """Custom RAG: always uses local document retrieval + Bedrock generation."""
        return await self._generate_with_local_docs(query, system_prompt, session_id)

    # ------------------------------------------------------------------
    # Document upload
    # ------------------------------------------------------------------

    async def upload_document(self, file_content: bytes, file_name: str) -> Dict[str, Any]:
        loop = asyncio.get_event_loop()
        try:
            s3_key = await loop.run_in_executor(
                self.executor,
                self.bedrock_client.upload_document_to_s3,
                file_content,
                file_name,
            )
            return {'success': True, 'message': 'Document uploaded successfully', 's3_key': s3_key, 'file_name': file_name}
        except Exception as e:
            logger.error(f"Document upload error: {e}")
            return {'success': False, 'message': f"Document upload failed: {str(e)}", 'file_name': file_name}

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    def _apply_config_overrides(
        self, max_tokens: Optional[int], temperature: Optional[float], top_k: Optional[int]
    ) -> Dict[str, Any]:
        original = {'MAX_TOKENS': config.MAX_TOKENS, 'TEMPERATURE': config.TEMPERATURE, 'TOP_K': config.TOP_K}
        if max_tokens:
            config.MAX_TOKENS = max_tokens
        if temperature is not None:
            config.TEMPERATURE = temperature
        if top_k:
            config.TOP_K = top_k
        return original

    def _restore_config_values(self, original_values: Dict[str, Any]) -> None:
        config.MAX_TOKENS = original_values['MAX_TOKENS']
        config.TEMPERATURE = original_values['TEMPERATURE']
        config.TOP_K = original_values['TOP_K']

    def _parse_retrieval_results(self, kb_response: Dict[str, Any]) -> List[RetrievalResult]:
        sources = []
        for result in kb_response.get('retrievalResults', []):
            content = result.get('content', {}).get('text', '')
            metadata = result.get('metadata', {})
            score = result.get('score', 0.0)
            location = result.get('location', {})
            uri = location.get('s3Location', {}).get('uri')
            sources.append(RetrievalResult(
                content=content, metadata=metadata,
                source=DocumentSource(source_id=uri, uri=uri, score=score),
            ))
        return sources

    def _parse_citations(self, citations: List[Dict[str, Any]]) -> List[RetrievalResult]:
        sources = []
        for citation in citations:
            for ref in citation.get('retrievedReferences', []):
                content = ref.get('content', {}).get('text', '')
                metadata = ref.get('metadata', {})
                uri = ref.get('location', {}).get('s3Location', {}).get('uri')
                sources.append(RetrievalResult(
                    content=content, metadata=metadata,
                    source=DocumentSource(source_id=uri, uri=uri),
                ))
        return sources

    def _create_content_summary(self, sources: List[RetrievalResult]) -> str:
        parts = []
        for i, s in enumerate(sources[:5], 1):
            excerpt = s.content[:200] + '...' if len(s.content) > 200 else s.content
            score_str = f" (score: {s.source.score:.2f})" if s.source and s.source.score else ''
            parts.append(f"{i}. {excerpt}{score_str}")
        return '\n\n'.join(parts)
