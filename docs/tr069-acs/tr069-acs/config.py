"""
Configuration settings for TR-069 ACS
"""
import os
from typing import Optional

class Settings:
    """Application settings"""
    
    # Server settings
    HOST: str = os.getenv("ACS_HOST", "0.0.0.0")
    PORT: int = int(os.getenv("ACS_PORT", "8080"))
    
    # Database
    DATABASE_URL: str = os.getenv("DATABASE_URL", "sqlite:///tr069_acs.db")
    
    # CWMP settings
    CWMP_ENDPOINT: str = "/cwmp"
    MAX_ENVELOPES: int = 1
    
    # Session timeout (seconds)
    SESSION_TIMEOUT: int = 30
    
    # Connection Request
    CONNECTION_REQUEST_TIMEOUT: int = 5
    
    # Device settings
    DEVICE_OFFLINE_THRESHOLD: int = 600  # seconds (10 minutes)
    
    # Periodic inform interval (default for new devices)
    DEFAULT_INFORM_INTERVAL: int = 300  # seconds (5 minutes)
    
    # Task settings
    MAX_TASK_RETRIES: int = 3
    TASK_RETRY_DELAY: int = 60  # seconds
    
    # API settings
    API_PREFIX: str = "/api"
    
    # Security (for production)
    ENABLE_AUTH: bool = os.getenv("ENABLE_AUTH", "false").lower() == "true"
    API_KEY: Optional[str] = os.getenv("API_KEY")
    
    # HTTPS settings (for production)
    SSL_CERTFILE: Optional[str] = os.getenv("SSL_CERTFILE")
    SSL_KEYFILE: Optional[str] = os.getenv("SSL_KEYFILE")
    
    # Logging
    LOG_LEVEL: str = os.getenv("LOG_LEVEL", "INFO")
    LOG_FILE: Optional[str] = os.getenv("LOG_FILE")
    
    # CORS
    CORS_ORIGINS: list = ["*"]  # In production, specify allowed origins
    
    # Features
    ENABLE_FIRMWARE_UPGRADE: bool = True
    ENABLE_FILE_TRANSFER: bool = True
    ENABLE_DIAGNOSTICS: bool = True


settings = Settings()
