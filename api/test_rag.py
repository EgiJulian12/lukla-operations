#!/usr/bin/env python3
"""
Test script for RAG API endpoints
"""
import requests
import json
import time
from typing import Dict, Any

API_BASE_URL = "http://localhost:8080"

def test_health():
    """Test health endpoint"""
    print("🔍 Testing health endpoint...")
    response = requests.get(f"{API_BASE_URL}/health")
    print(f"Status: {response.status_code}")
    data = response.json()
    print(f"Health: {json.dumps(data, indent=2)}")
    return response.status_code == 200

def test_config():
    """Test config endpoint"""
    print("\n⚙️ Testing config endpoint...")
    response = requests.get(f"{API_BASE_URL}/config")
    print(f"Status: {response.status_code}")
    data = response.json()
    print(f"Config: {json.dumps(data, indent=2)}")
    return response.status_code == 200

def test_models():
    """Test models endpoint"""
    print("\n📋 Testing models endpoint...")
    try:
        response = requests.get(f"{API_BASE_URL}/models")
        print(f"Status: {response.status_code}")
        data = response.json()
        print(f"Found {data.get('count', 0)} models")
        return response.status_code == 200
    except Exception as e:
        print(f"Error: {e}")
        return False

def test_generate_only():
    """Test generate only mode"""
    print("\n🤖 Testing generate-only mode...")
    payload = {
        "query": "What is manufacturing resource planning and how does it help in production management?",
        "mode": "generate_only",
        "max_tokens": 500,
        "temperature": 0.1
    }
    
    try:
        response = requests.post(f"{API_BASE_URL}/ask", json=payload)
        print(f"Status: {response.status_code}")
        
        if response.status_code == 200:
            data = response.json()
            print(f"Answer: {data['answer'][:200]}...")
            print(f"Model: {data['model_id']}")
            print(f"Session ID: {data['session_id']}")
            return True
        else:
            print(f"Error: {response.text}")
            return False
    except Exception as e:
        print(f"Error: {e}")
        return False

def test_retrieve_and_generate():
    """Test retrieve and generate mode (requires Knowledge Base)"""
    print("\n📚 Testing retrieve-and-generate mode...")
    payload = {
        "query": "How do I manage inventory in a manufacturing operation?",
        "mode": "retrieve_and_generate",
        "max_tokens": 1000,
        "temperature": 0.1,
        "top_k": 3
    }
    
    try:
        response = requests.post(f"{API_BASE_URL}/ask", json=payload)
        print(f"Status: {response.status_code}")
        
        if response.status_code == 200:
            data = response.json()
            print(f"Answer: {data['answer'][:200]}...")
            print(f"Sources found: {len(data['sources'])}")
            print(f"Model: {data['model_id']}")
            return True
        else:
            print(f"Error: {response.text}")
            return False
    except Exception as e:
        print(f"Error: {e}")
        return False

def test_custom_rag():
    """Test custom RAG mode"""
    print("\n🔧 Testing custom-rag mode...")
    payload = {
        "query": "What are the best practices for quality control in manufacturing?",
        "mode": "custom_rag",
        "system_prompt": "You are an expert manufacturing consultant. Provide detailed, practical advice.",
        "max_tokens": 800,
        "temperature": 0.2,
        "top_k": 5
    }
    
    try:
        response = requests.post(f"{API_BASE_URL}/ask", json=payload)
        print(f"Status: {response.status_code}")
        
        if response.status_code == 200:
            data = response.json()
            print(f"Answer: {data['answer'][:200]}...")
            print(f"Sources found: {len(data['sources'])}")
            print(f"Mode: {data['mode']}")
            return True
        else:
            print(f"Error: {response.text}")
            return False
    except Exception as e:
        print(f"Error: {e}")
        return False

def test_upload_document():
    """Test document upload (requires sample file)"""
    print("\n📄 Testing document upload...")
    
    # Create a sample text file
    sample_content = """
    # Manufacturing Operations Guide
    
    ## Introduction
    This guide covers the basic principles of manufacturing operations management.
    
    ## Key Processes
    1. Production Planning
    2. Inventory Management
    3. Quality Control
    4. Resource Allocation
    
    ## Best Practices
    - Implement lean manufacturing principles
    - Use data-driven decision making
    - Maintain continuous improvement culture
    - Focus on customer satisfaction
    """
    
    try:
        files = {
            'file': ('manufacturing_guide.txt', sample_content, 'text/plain')
        }
        
        response = requests.post(f"{API_BASE_URL}/upload", files=files)
        print(f"Status: {response.status_code}")
        
        if response.status_code == 200:
            data = response.json()
            print(f"Upload success: {data['success']}")
            print(f"Message: {data['message']}")
            print(f"S3 Key: {data.get('s3_key', 'N/A')}")
            return True
        else:
            print(f"Error: {response.text}")
            return False
    except Exception as e:
        print(f"Error: {e}")
        return False

def main():
    """Run all tests"""
    print("🚀 Starting RAG API Tests")
    print("=" * 50)
    
    results = []
    
    # Run tests
    results.append(("Health Check", test_health()))
    results.append(("Config Check", test_config()))
    results.append(("Models List", test_models()))
    results.append(("Generate Only", test_generate_only()))
    results.append(("Retrieve & Generate", test_retrieve_and_generate()))
    results.append(("Custom RAG", test_custom_rag()))
    results.append(("Document Upload", test_upload_document()))
    
    # Print summary
    print("\n" + "=" * 50)
    print("📊 Test Summary")
    print("=" * 50)
    
    passed = 0
    for test_name, result in results:
        status = "✅ PASS" if result else "❌ FAIL"
        print(f"{status} {test_name}")
        if result:
            passed += 1
    
    print(f"\nTotal: {passed}/{len(results)} tests passed")
    
    if passed == len(results):
        print("🎉 All tests passed!")
    else:
        print("⚠️ Some tests failed - check the output above for details")

if __name__ == "__main__":
    main()