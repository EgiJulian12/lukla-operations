from fastapi import FastAPI, HTTPException, UploadFile, File, Depends
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
import logging
from datetime import datetime
import asyncio
from typing import Optional

# Internal imports
from config import config
from models import (
    QueryRequest, RAGResponse, DocumentUploadResponse, 
    HealthResponse, ErrorResponse
)
from rag_service import RAGService
from bedrock_client import BedrockClient

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Initialize FastAPI app
app = FastAPI(
    title=config.API_TITLE,
    description=config.API_DESCRIPTION,
    version=config.API_VERSION,
    docs_url="/docs",
    redoc_url="/redoc"
)

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Configure this properly for production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Global instances
rag_service = RAGService()
bedrock_client = BedrockClient()

async def validate_config():
    """Dependency to validate AWS configuration"""
    if not config.validate_aws_config():
        raise HTTPException(
            status_code=500,
            detail="AWS configuration is incomplete. Check AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, and AWS_BUCKET environment variables."
        )

@app.on_event("startup")
async def startup_event():
    """Application startup event"""
    logger.info("Starting Lukla Operations RAG API...")
    
    # Validate configuration
    if not config.validate_aws_config():
        logger.error("AWS configuration validation failed")
        # Don't crash the app, just log the error
    else:
        logger.info("AWS configuration validated successfully")
    
    logger.info(f"API started successfully - {config.API_TITLE} v{config.API_VERSION}")

@app.on_event("shutdown")
async def shutdown_event():
    """Application shutdown event"""
    logger.info("Shutting down RAG API...")

@app.exception_handler(Exception)
async def global_exception_handler(request, exc):
    """Global exception handler"""
    logger.error(f"Unhandled exception: {exc}")
    return JSONResponse(
        status_code=500,
        content=ErrorResponse(
            error="Internal server error",
            detail=str(exc)
        ).model_dump()
    )

@app.get("/", response_model=dict)
async def root():
    """Root endpoint"""
    return {
        "message": "Lukla Operations RAG API",
        "version": config.API_VERSION,
        "status": "active",
        "endpoints": {
            "health": "/health",
            "ask": "/ask",
            "upload": "/upload",
            "docs": "/docs"
        }
    }

@app.get("/health", response_model=HealthResponse)
async def health_check():
    """Health check endpoint"""
    aws_configured = config.validate_aws_config()
    bedrock_accessible = False
    
    # Test Bedrock connectivity
    if aws_configured:
        try:
            # Simple test to check if Bedrock is accessible
            response = await asyncio.get_event_loop().run_in_executor(
                None, 
                lambda: bedrock_client.session.client('bedrock').list_foundation_models()
            )
            bedrock_accessible = True
            logger.info("Bedrock connectivity test passed")
        except Exception as e:
            logger.warning(f"Bedrock connectivity test failed: {e}")
            bedrock_accessible = False
    
    return HealthResponse(
        status="healthy" if aws_configured and bedrock_accessible else "degraded",
        aws_configured=aws_configured,
        bedrock_accessible=bedrock_accessible,
        timestamp=datetime.now().isoformat()
    )

@app.post("/ask", response_model=RAGResponse)
async def ask_question(
    request: QueryRequest,
):
    """
    Main RAG endpoint for asking questions
    
    This endpoint supports multiple RAG modes:
    - retrieve_only: Only retrieve relevant documents
    - generate_only: Only generate response (no retrieval, no AWS S3 needed)
    - retrieve_and_generate: Use Bedrock's built-in RAG (recommended)
    - custom_rag: Custom RAG implementation
    """
    # Only modes that touch S3/KB require full AWS config
    modes_needing_aws = {"retrieve_only", "retrieve_and_generate", "custom_rag"}
    if request.mode in modes_needing_aws and not config.validate_aws_config():
        raise HTTPException(
            status_code=500,
            detail="AWS configuration is incomplete for the requested mode."
        )

    try:
        logger.info(f"Processing query: {request.query[:100]}... (mode: {request.mode})")
        
        response = await rag_service.process_query(
            query=request.query,
            mode=request.mode,
            knowledge_base_id=request.knowledge_base_id,
            system_prompt=request.system_prompt,
            max_tokens=request.max_tokens,
            temperature=request.temperature,
            top_k=request.top_k
        )
        
        logger.info(f"Query processed successfully (session: {response.session_id})")
        return response
        
    except Exception as e:
        logger.error(f"Query processing error: {e}")
        raise HTTPException(
            status_code=500,
            detail=f"Query processing failed: {str(e)}"
        )

@app.post("/upload", response_model=DocumentUploadResponse)
async def upload_document(
    file: UploadFile = File(...),
    _: None = Depends(validate_config)
):
    """
    Upload document to S3 for Knowledge Base ingestion
    
    Supported formats: PDF, TXT, DOC, DOCX, HTML, MD
    Max file size: 50MB
    """
    try:
        # Validate file type
        allowed_types = [
            'application/pdf',
            'text/plain',
            'text/markdown', 
            'text/html',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ]
        
        if file.content_type not in allowed_types:
            raise HTTPException(
                status_code=400,
                detail=f"Unsupported file type: {file.content_type}. Supported types: {allowed_types}"
            )
        
        # Validate file size (50MB limit)
        MAX_FILE_SIZE = 50 * 1024 * 1024  # 50MB
        file_content = await file.read()
        
        if len(file_content) > MAX_FILE_SIZE:
            raise HTTPException(
                status_code=400,
                detail=f"File too large. Maximum size: {MAX_FILE_SIZE / (1024*1024)}MB"
            )
        
        logger.info(f"Uploading document: {file.filename} ({len(file_content)} bytes)")
        
        # Upload document
        result = await rag_service.upload_document(file_content, file.filename)
        
        if result["success"]:
            logger.info(f"Document uploaded successfully: {file.filename}")
            return DocumentUploadResponse(**result)
        else:
            raise HTTPException(status_code=500, detail=result["message"])
            
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Document upload error: {e}")
        raise HTTPException(
            status_code=500,
            detail=f"Document upload failed: {str(e)}"
        )

@app.get("/models")
async def list_available_models(_: None = Depends(validate_config)):
    """List available Bedrock models"""
    try:
        response = await asyncio.get_event_loop().run_in_executor(
            None,
            lambda: bedrock_client.session.client('bedrock').list_foundation_models()
        )
        
        models = []
        for model in response.get('modelSummaries', []):
            models.append({
                'modelId': model.get('modelId'),
                'modelName': model.get('modelName'),
                'providerName': model.get('providerName'),
                'inputModalities': model.get('inputModalities', []),
                'outputModalities': model.get('outputModalities', []),
                'responseStreamingSupported': model.get('responseStreamingSupported', False)
            })
        
        return {
            "models": models,
            "count": len(models),
            "region": config.BEDROCK_REGION
        }
        
    except Exception as e:
        logger.error(f"Error listing models: {e}")
        raise HTTPException(
            status_code=500,
            detail=f"Failed to list models: {str(e)}"
        )

@app.get("/config")
async def get_config():
    """Get current API configuration (excluding sensitive data)"""
    return {
        "bedrock_region": config.BEDROCK_REGION,
        "bedrock_model_id": config.BEDROCK_MODEL_ID,
        "embedding_model_id": config.BEDROCK_EMBEDDING_MODEL_ID,
        "max_tokens": config.MAX_TOKENS,
        "temperature": config.TEMPERATURE,
        "top_k": config.TOP_K,
        "knowledge_base_configured": bool(config.KNOWLEDGE_BASE_ID),
        "s3_bucket_configured": bool(config.AWS_BUCKET)
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "main:app",
        host="0.0.0.0",
        port=8080,
        reload=True,
        log_level="info"
    )