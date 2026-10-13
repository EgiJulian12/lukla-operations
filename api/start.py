#!/usr/bin/env python3
"""
Startup script for Lukla Operations RAG API
"""
import uvicorn
import os
import sys
from pathlib import Path

def main():
    """Main startup function"""
    # Ensure we're in the api directory
    os.chdir(Path(__file__).parent)
    
    # Configuration for uvicorn
    config = {
        "app": "main:app",
        "host": "0.0.0.0",
        "port": 8080,
        "reload": True,
        "log_level": "info",
        "access_log": True,
    }
    
    print("🚀 Starting Lukla Operations RAG API...")
    print(f"📍 Server will be available at: http://localhost:8080")
    print(f"📚 API Documentation: http://localhost:8080/docs")
    print(f"🔍 Health Check: http://localhost:8080/health")
    print(f"❓ Ask Endpoint: http://localhost:8080/ask")
    print(f"📁 Upload Endpoint: http://localhost:8080/upload")
    print("=" * 50)
    
    try:
        uvicorn.run(**config)
    except KeyboardInterrupt:
        print("\n👋 Shutting down RAG API...")
        sys.exit(0)
    except Exception as e:
        print(f"❌ Failed to start server: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()