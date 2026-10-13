#!/usr/bin/env python3
"""
Test script to find working Bedrock models
"""
import boto3
import json
from config import config

def test_model(model_id, region='us-east-1'):
    """Test if a model works with simple invocation"""
    try:
        client = boto3.client('bedrock-runtime', region_name=region,
                            aws_access_key_id=config.AWS_ACCESS_KEY_ID,
                            aws_secret_access_key=config.AWS_SECRET_ACCESS_KEY)
        
        response = client.converse(
            modelId=model_id,
            messages=[{"role": "user", "content": [{"text": "Hello"}]}],
            inferenceConfig={"maxTokens": 100}
        )
        
        print(f"✅ {model_id} - SUCCESS")
        return True
    except Exception as e:
        print(f"❌ {model_id} - ERROR: {e}")
        return False

def main():
    """Test different models"""
    
    # List of models to test
    models_to_test = [
        "anthropic.claude-3-sonnet-20240229-v1:0",
        "anthropic.claude-3-haiku-20240307-v1:0", 
        "anthropic.claude-instant-v1",
        "amazon.titan-text-express-v1",
        "amazon.titan-text-lite-v1",
        "meta.llama2-13b-chat-v1",
        "meta.llama2-70b-chat-v1",
        "cohere.command-text-v14",
        "ai21.j2-mid-v1",
        "ai21.j2-ultra-v1"
    ]
    
    print("🔍 Testing Bedrock Models in us-east-1:")
    print("=" * 50)
    
    working_models = []
    for model_id in models_to_test:
        if test_model(model_id):
            working_models.append(model_id)
    
    print("\n" + "=" * 50)
    print(f"✅ Working models ({len(working_models)}):")
    for model in working_models:
        print(f"  - {model}")
    
    if working_models:
        print(f"\n💡 Recommended: {working_models[0]}")
    else:
        print("\n❌ No working models found")

if __name__ == "__main__":
    main()