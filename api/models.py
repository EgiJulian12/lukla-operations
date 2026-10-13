from pydantic import BaseModel, Field
from typing import List, Optional, Dict, Any
from enum import Enum

class RAGMode(str, Enum):
    """RAG operation modes"""
    RETRIEVE_ONLY = "retrieve_only"
    GENERATE_ONLY = "generate_only" 
    RETRIEVE_AND_GENERATE = "retrieve_and_generate"
    CUSTOM_RAG = "custom_rag"

class QueryRequest(BaseModel):
    """Request model for RAG queries"""
    query: str = Field(..., description="User question or query", min_length=1)
    mode: RAGMode = Field(default=RAGMode.RETRIEVE_AND_GENERATE, description="RAG operation mode")
    knowledge_base_id: Optional[str] = Field(None, description="Specific Knowledge Base ID to use")
    system_prompt: Optional[str] = Field(None, description="Custom system prompt")
    max_tokens: Optional[int] = Field(None, description="Maximum tokens for response", ge=1, le=100000)
    temperature: Optional[float] = Field(None, description="Model temperature", ge=0.0, le=1.0)
    top_k: Optional[int] = Field(None, description="Number of documents to retrieve", ge=1, le=100)

class DocumentSource(BaseModel):
    """Document source information"""
    source_id: Optional[str] = None
    title: Optional[str] = None
    uri: Optional[str] = None
    excerpt: Optional[str] = None
    score: Optional[float] = None

class RetrievalResult(BaseModel):
    """Individual retrieval result"""
    content: str
    metadata: Dict[str, Any] = {}
    source: Optional[DocumentSource] = None

class RAGResponse(BaseModel):
    """Response model for RAG operations"""
    answer: str = Field(..., description="Generated answer")
    sources: List[RetrievalResult] = Field(default_factory=list, description="Retrieved document sources")
    mode: RAGMode = Field(..., description="RAG mode used")
    model_id: str = Field(..., description="Bedrock model used")
    session_id: Optional[str] = Field(None, description="Session identifier")
    usage: Optional[Dict[str, Any]] = Field(None, description="Token usage information")

class DocumentUploadRequest(BaseModel):
    """Request for document upload"""
    file_name: str = Field(..., description="Name of the file")
    content_type: Optional[str] = Field(None, description="MIME type of the file")

class DocumentUploadResponse(BaseModel):
    """Response for document upload"""
    success: bool
    message: str
    s3_key: Optional[str] = None
    file_name: str

class HealthResponse(BaseModel):
    """Health check response"""
    status: str
    aws_configured: bool
    bedrock_accessible: bool
    timestamp: str

class ErrorResponse(BaseModel):
    """Error response model"""
    error: str
    detail: Optional[str] = None
    error_code: Optional[str] = None