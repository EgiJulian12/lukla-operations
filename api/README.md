# Lukla Operations RAG API

FastAPI-based RAG (Retrieval-Augmented Generation) endpoint using Amazon Bedrock for the Lukla Operations system.

## Features

- 🤖 **Multiple RAG Modes**: retrieve_only, generate_only, retrieve_and_generate, custom_rag
- 📚 **Knowledge Base Integration**: Amazon Bedrock Knowledge Base support
- 🚀 **Claude 3.5 Sonnet**: Latest Claude model with 200k context window
- 📄 **Document Upload**: Support for PDF, TXT, DOC, DOCX, HTML, MD files
- 🔒 **AWS Integration**: Secure integration with AWS services
- 📊 **Health Monitoring**: Built-in health checks and monitoring
- 🌐 **CORS Support**: Cross-origin resource sharing enabled
- 📖 **Auto Documentation**: Swagger/OpenAPI docs at `/docs`

## Quick Start

### 1. Install Dependencies

```bash
cd api
pip install -r requirements.txt
```

### 2. Configure Environment

Edit `api/.env` with your AWS credentials:

```env
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=your-s3-bucket
BEDROCK_REGION=ap-southeast-1
```

### 3. Start the Server

```bash
python start.py
```

The API will be available at:
- **Main API**: http://localhost:8080
- **Documentation**: http://localhost:8080/docs
- **Health Check**: http://localhost:8080/health

## API Endpoints

### `POST /ask` - RAG Query

Ask questions using different RAG modes.

**Request Body:**
```json
{
  "query": "What are the manufacturing processes in Lukla Operations?",
  "mode": "retrieve_and_generate",
  "system_prompt": "You are an expert in manufacturing operations",
  "max_tokens": 4000,
  "temperature": 0.1,
  "top_k": 5
}
```

**RAG Modes:**
- `retrieve_only`: Only retrieve relevant documents
- `generate_only`: Only generate response (no retrieval)
- `retrieve_and_generate`: Use Bedrock's built-in RAG (recommended)
- `custom_rag`: Custom RAG implementation

**Response:**
```json
{
  "answer": "Based on the documents, Lukla Operations has several key manufacturing processes...",
  "sources": [
    {
      "content": "Document excerpt...",
      "metadata": {},
      "source": {
        "source_id": "s3://bucket/documents/process-guide.pdf",
        "title": "Process Guide",
        "score": 0.95
      }
    }
  ],
  "mode": "retrieve_and_generate",
  "model_id": "us.anthropic.claude-3-5-sonnet-20241022-v2:0",
  "session_id": "uuid-session-id"
}
```

### `POST /upload` - Document Upload

Upload documents to S3 for Knowledge Base ingestion.

**Request:** Multipart form with file upload
**Supported formats:** PDF, TXT, DOC, DOCX, HTML, MD
**Max size:** 50MB

**Response:**
```json
{
  "success": true,
  "message": "Document uploaded successfully",
  "s3_key": "documents/filename.pdf",
  "file_name": "filename.pdf"
}
```

### `GET /health` - Health Check

Check API and AWS service health.

**Response:**
```json
{
  "status": "healthy",
  "aws_configured": true,
  "bedrock_accessible": true,
  "timestamp": "2024-10-13T10:00:00"
}
```

### `GET /models` - List Available Models

List all available Bedrock foundation models.

### `GET /config` - Get Configuration

Get current API configuration (non-sensitive data only).

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `AWS_ACCESS_KEY_ID` | AWS access key | Required |
| `AWS_SECRET_ACCESS_KEY` | AWS secret key | Required |
| `AWS_DEFAULT_REGION` | AWS region | `us-east-1` |
| `AWS_BUCKET` | S3 bucket for documents | Required |
| `BEDROCK_REGION` | Bedrock service region | `us-east-1` |
| `BEDROCK_MODEL_ID` | Claude model ID | `us.anthropic.claude-3-5-sonnet-20241022-v2:0` |
| `BEDROCK_EMBEDDING_MODEL_ID` | Embedding model ID | `amazon.titan-embed-text-v2:0` |
| `KNOWLEDGE_BASE_ID` | Bedrock Knowledge Base ID | Optional |
| `MAX_TOKENS` | Max output tokens | `4000` |
| `TEMPERATURE` | Model temperature | `0.1` |
| `TOP_K` | Number of documents to retrieve | `5` |

## Usage Examples

### Python Client Example

```python
import requests

# Ask a question
response = requests.post("http://localhost:8080/ask", json={
    "query": "How do I process a manufacturing order?",
    "mode": "retrieve_and_generate"
})

data = response.json()
print(f"Answer: {data['answer']}")
print(f"Sources: {len(data['sources'])} documents")
```

### JavaScript/Node.js Client Example

```javascript
const response = await fetch('http://localhost:8080/ask', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    query: 'What are the quality control procedures?',
    mode: 'retrieve_and_generate'
  })
});

const data = await response.json();
console.log('Answer:', data.answer);
```

### cURL Example

```bash
curl -X POST "http://localhost:8080/ask" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "Explain the inventory management system",
    "mode": "retrieve_and_generate",
    "temperature": 0.1,
    "max_tokens": 2000
  }'
```

## Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   FastAPI App   │    │   RAG Service    │    │  Bedrock Client │
│                 │ -> │                  │ -> │                 │
│ • Endpoints     │    │ • Query Processing│    │ • Converse API  │
│ • Validation    │    │ • Mode Handling   │    │ • Knowledge Base│
│ • Error Handling│    │ • Async Execution │    │ • S3 Upload     │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

## Security Best Practices

1. **Environment Variables**: Store sensitive data in `.env` files
2. **AWS IAM**: Use least-privilege IAM policies
3. **CORS**: Configure appropriate CORS origins for production
4. **Rate Limiting**: Implement rate limiting for production use
5. **Input Validation**: All inputs are validated using Pydantic models
6. **Error Handling**: Sensitive information is not exposed in error messages

## Troubleshooting

### Common Issues

1. **AWS Configuration Error**
   - Ensure AWS credentials are correctly set in `.env`
   - Verify AWS region is supported for Bedrock

2. **Model Not Available**
   - Check if the model is available in your region
   - Use `/models` endpoint to list available models

3. **Knowledge Base Not Found**
   - Verify `KNOWLEDGE_BASE_ID` is correct
   - Ensure Knowledge Base exists in the specified region

4. **File Upload Failed**
   - Check file format is supported
   - Verify S3 bucket permissions
   - Ensure file size is under 50MB limit

### Debugging

Enable debug logging by setting `DEBUG=true` in your `.env` file.

Check logs for detailed error information and API call traces.

## Development

### Project Structure

```
api/
├── main.py              # FastAPI application
├── config.py            # Configuration management
├── models.py            # Pydantic models
├── bedrock_client.py    # AWS Bedrock client
├── rag_service.py       # RAG business logic
├── start.py             # Startup script
├── requirements.txt     # Python dependencies
├── .env                 # Environment variables
└── README.md           # This file
```

### Adding New Features

1. Define new Pydantic models in `models.py`
2. Add business logic to `rag_service.py`
3. Create new endpoints in `main.py`
4. Update documentation

## License

Part of the Lukla Operations system.