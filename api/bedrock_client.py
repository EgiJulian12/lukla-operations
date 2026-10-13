import boto3
import json
import re
from typing import List, Dict, Any, Optional
from botocore.exceptions import ClientError
from config import config
import logging

logger = logging.getLogger(__name__)


class BedrockClient:
    """AWS Bedrock client with local-document RAG fallback."""

    def __init__(self):
        # Bedrock runtime in us-east-1 (only region that works for this account)
        self._bedrock_session = boto3.Session(
            aws_access_key_id=config.AWS_ACCESS_KEY_ID,
            aws_secret_access_key=config.AWS_SECRET_ACCESS_KEY,
            region_name=config.BEDROCK_REGION,
        )
        self.bedrock_runtime = self._bedrock_session.client('bedrock-runtime')

        # S3 + Bedrock Agent in ap-southeast-1
        self._ap_session = boto3.Session(
            aws_access_key_id=config.AWS_ACCESS_KEY_ID,
            aws_secret_access_key=config.AWS_SECRET_ACCESS_KEY,
            region_name=config.AWS_DEFAULT_REGION,
        )
        self.s3_client = self._ap_session.client('s3')
        self.bedrock_agent_runtime = self._ap_session.client('bedrock-agent-runtime')

        # Keep a full reference for health-check use
        self.session = self._bedrock_session

        # In-memory document cache: {s3_key: [chunk, ...]}
        self._doc_cache: Dict[str, List[str]] = {}
        self._load_documents_from_s3()

    # ------------------------------------------------------------------
    # Document loading & chunking
    # ------------------------------------------------------------------

    def _load_documents_from_s3(self) -> None:
        """Download all text documents from S3 and chunk them into memory."""
        try:
            response = self.s3_client.list_objects_v2(Bucket=config.AWS_BUCKET)
            objects = response.get('Contents', [])
            if not objects:
                logger.warning("S3 bucket is empty — no documents loaded")
                return

            for obj in objects:
                key = obj['Key']
                if not self._is_text_document(key):
                    continue
                try:
                    body = self.s3_client.get_object(Bucket=config.AWS_BUCKET, Key=key)
                    text = body['Body'].read().decode('utf-8', errors='replace')
                    chunks = self._chunk_text(text)
                    self._doc_cache[key] = chunks
                    logger.info(f"Loaded {len(chunks)} chunks from s3://{config.AWS_BUCKET}/{key}")
                except Exception as e:
                    logger.warning(f"Failed to load {key}: {e}")

            total_chunks = sum(len(c) for c in self._doc_cache.values())
            logger.info(f"Document cache ready: {len(self._doc_cache)} files, {total_chunks} total chunks")

        except Exception as e:
            logger.error(f"S3 document load error: {e}")

    def _is_text_document(self, key: str) -> bool:
        text_extensions = ('.md', '.txt', '.html', '.htm', '.json', '.csv')
        return key.lower().endswith(text_extensions)

    def _chunk_text(self, text: str, chunk_size: int = 600, overlap: int = 100) -> List[str]:
        """Split text into overlapping chunks, preferring paragraph boundaries."""
        # Normalise whitespace
        text = re.sub(r'\r\n', '\n', text)
        text = re.sub(r'\n{3,}', '\n\n', text)

        # Split on paragraph breaks first
        paragraphs = [p.strip() for p in re.split(r'\n\n+', text) if p.strip()]

        chunks: List[str] = []
        current_chunk = ''

        for para in paragraphs:
            if len(current_chunk) + len(para) + 1 <= chunk_size:
                current_chunk = (current_chunk + '\n\n' + para).strip()
            else:
                if current_chunk:
                    chunks.append(current_chunk)
                # If single paragraph is bigger than chunk_size, split by sentences
                if len(para) > chunk_size:
                    sentences = re.split(r'(?<=[.!?])\s+', para)
                    buf = ''
                    for s in sentences:
                        if len(buf) + len(s) + 1 <= chunk_size:
                            buf = (buf + ' ' + s).strip()
                        else:
                            if buf:
                                chunks.append(buf)
                            buf = s
                    if buf:
                        current_chunk = buf
                    else:
                        current_chunk = ''
                else:
                    # carry overlap from previous chunk
                    tail = current_chunk[-overlap:] if current_chunk else ''
                    current_chunk = (tail + '\n\n' + para).strip() if tail else para

        if current_chunk:
            chunks.append(current_chunk)

        return chunks

    def retrieve_relevant_chunks(self, query: str, top_k: int = 5) -> List[Dict[str, Any]]:
        """
        Simple keyword-based retrieval from cached document chunks.
        Returns the top_k most relevant chunks with source metadata.
        """
        if not self._doc_cache:
            return []

        query_terms = set(re.findall(r'\b\w{3,}\b', query.lower()))

        scored: List[tuple] = []
        for key, chunks in self._doc_cache.items():
            for i, chunk in enumerate(chunks):
                chunk_lower = chunk.lower()
                chunk_terms = set(re.findall(r'\b\w{3,}\b', chunk_lower))
                overlap = len(query_terms & chunk_terms)
                if overlap > 0:
                    # Boost score for title/header matches
                    header_boost = 1.5 if re.search(r'^#+\s', chunk[:50], re.M) else 1.0
                    score = overlap * header_boost
                    scored.append((score, key, i, chunk))

        scored.sort(key=lambda x: x[0], reverse=True)

        results = []
        for score, key, idx, chunk in scored[:top_k]:
            results.append({
                'content': chunk,
                'source': key,
                'chunk_index': idx,
                'score': round(score, 2),
            })

        return results

    def reload_documents(self) -> None:
        """Force-reload all documents from S3 (e.g. after upload)."""
        self._doc_cache.clear()
        self._load_documents_from_s3()

    # ------------------------------------------------------------------
    # Model invocation
    # ------------------------------------------------------------------

    def invoke_model(self, prompt: str, system_prompt: Optional[str] = None) -> str:
        """
        Call the configured Bedrock model via Converse API.
        Raises on failure — no silent mock fallback.
        """
        messages = [{'role': 'user', 'content': [{'text': prompt}]}]
        system = [{'text': system_prompt}] if system_prompt else []
        inference_config = {
            'maxTokens': config.MAX_TOKENS,
            'temperature': config.TEMPERATURE,
        }

        response = self.bedrock_runtime.converse(
            modelId=config.BEDROCK_MODEL_ID,
            messages=messages,
            system=system,
            inferenceConfig=inference_config,
        )
        return response['output']['message']['content'][0]['text']

    # Keep old name for compatibility with rag_service.py
    def invoke_claude(self, prompt: str, system_prompt: Optional[str] = None) -> str:
        return self.invoke_model(prompt, system_prompt)

    # ------------------------------------------------------------------
    # Knowledge Base (used only when KNOWLEDGE_BASE_ID is configured)
    # ------------------------------------------------------------------

    def query_knowledge_base(self, query: str, kb_id: Optional[str] = None) -> Dict[str, Any]:
        kb_id = kb_id or config.KNOWLEDGE_BASE_ID
        if not kb_id:
            raise Exception("Knowledge Base ID not configured")

        response = self.bedrock_agent_runtime.retrieve(
            knowledgeBaseId=kb_id,
            retrievalQuery={'text': query},
            retrievalConfiguration={
                'vectorSearchConfiguration': {'numberOfResults': config.TOP_K}
            },
        )
        return response

    def retrieve_and_generate(self, query: str, kb_id: Optional[str] = None) -> Dict[str, Any]:
        kb_id = kb_id or config.KNOWLEDGE_BASE_ID
        if not kb_id:
            raise Exception("Knowledge Base ID not configured")

        response = self.bedrock_agent_runtime.retrieve_and_generate(
            input={'text': query},
            retrieveAndGenerateConfiguration={
                'type': 'KNOWLEDGE_BASE',
                'knowledgeBaseConfiguration': {
                    'knowledgeBaseId': kb_id,
                    'modelArn': f'arn:aws:bedrock:{config.BEDROCK_REGION}::foundation-model/{config.BEDROCK_MODEL_ID}',
                    'retrievalConfiguration': {
                        'vectorSearchConfiguration': {'numberOfResults': config.TOP_K}
                    },
                },
            },
        )
        return response

    # ------------------------------------------------------------------
    # S3 upload
    # ------------------------------------------------------------------

    def upload_document_to_s3(self, file_content: bytes, file_name: str) -> str:
        s3_key = f"documents/{file_name}"
        self.s3_client.put_object(Bucket=config.AWS_BUCKET, Key=s3_key, Body=file_content)
        logger.info(f"Uploaded to S3: {s3_key}")
        # Refresh cache so newly uploaded doc is queryable immediately
        self.reload_documents()
        return s3_key

    def get_embeddings(self, text: str) -> List[float]:
        body = json.dumps({'inputText': text, 'dimensions': 1024, 'normalize': True})
        response = self.bedrock_runtime.invoke_model(
            modelId=config.BEDROCK_EMBEDDING_MODEL_ID, body=body
        )
        return json.loads(response['body'].read()).get('embedding', [])
