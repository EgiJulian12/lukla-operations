import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

class Config:
    """Configuration settings for the RAG API"""
    
    # AWS Configuration
    AWS_ACCESS_KEY_ID = os.getenv('AWS_ACCESS_KEY_ID')
    AWS_SECRET_ACCESS_KEY = os.getenv('AWS_SECRET_ACCESS_KEY') 
    AWS_DEFAULT_REGION = os.getenv('AWS_DEFAULT_REGION', 'us-east-1')
    AWS_BUCKET = os.getenv('AWS_BUCKET')
    
    # Bedrock Configuration
    BEDROCK_REGION = os.getenv('BEDROCK_REGION', 'us-east-1')
    BEDROCK_MODEL_ID = os.getenv('BEDROCK_MODEL_ID', 'us.anthropic.claude-3-5-sonnet-20241022-v2:0')
    BEDROCK_EMBEDDING_MODEL_ID = os.getenv('BEDROCK_EMBEDDING_MODEL_ID', 'amazon.titan-embed-text-v2:0')
    
    # Knowledge Base Configuration (if using Bedrock Knowledge Base)
    KNOWLEDGE_BASE_ID = os.getenv('KNOWLEDGE_BASE_ID')
    
    # API Configuration
    API_TITLE = "Lukla Operations RAG API"
    API_DESCRIPTION = "RAG endpoint using Amazon Bedrock for Lukla Operations"
    API_VERSION = "1.0.0"
    
    # RAG Settings
    MAX_TOKENS = int(os.getenv('MAX_TOKENS', '4000'))
    TEMPERATURE = float(os.getenv('TEMPERATURE', '0.1'))
    TOP_K = int(os.getenv('TOP_K', '5'))
    
    @classmethod
    def validate_aws_config(cls) -> bool:
        """Validate that required AWS configuration is present"""
        required_vars = [
            cls.AWS_ACCESS_KEY_ID,
            cls.AWS_SECRET_ACCESS_KEY,
            cls.AWS_BUCKET
        ]
        return all(var is not None and var.strip() != '' for var in required_vars)

config = Config()