"""
Database models for TR-069 ACS
"""
from sqlalchemy import create_engine, Column, String, DateTime, Integer, Text, Boolean, JSON
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
from datetime import datetime

Base = declarative_base()


class Device(Base):
    """Device (CPE) model"""
    __tablename__ = 'devices'
    
    id = Column(String(100), primary_key=True)  # Composite: OUI-ProductClass-SerialNumber
    manufacturer = Column(String(100))
    oui = Column(String(10))
    product_class = Column(String(100))
    serial_number = Column(String(100))
    
    # Connection info
    ip_address = Column(String(50))
    connection_request_url = Column(String(500))
    connection_request_username = Column(String(100))
    connection_request_password = Column(String(100))
    
    # Status
    last_inform = Column(DateTime)
    first_seen = Column(DateTime, default=datetime.utcnow)
    online = Column(Boolean, default=False)
    
    # Software/Hardware info
    software_version = Column(String(100))
    hardware_version = Column(String(100))
    
    # Tags for organization
    tags = Column(JSON, default=list)
    
    # Custom metadata
    metadata = Column(JSON, default=dict)


class Parameter(Base):
    """Device parameter/data model"""
    __tablename__ = 'parameters'
    
    id = Column(Integer, primary_key=True, autoincrement=True)
    device_id = Column(String(100), index=True)
    name = Column(String(500))
    value = Column(Text)
    type = Column(String(50))
    writable = Column(Boolean, default=False)
    last_updated = Column(DateTime, default=datetime.utcnow)


class Task(Base):
    """Pending tasks/commands for devices"""
    __tablename__ = 'tasks'
    
    id = Column(Integer, primary_key=True, autoincrement=True)
    device_id = Column(String(100), index=True)
    task_type = Column(String(50))  # get_params, set_params, reboot, factory_reset, etc.
    parameters = Column(JSON)  # Task-specific parameters
    status = Column(String(20), default='pending')  # pending, sent, completed, failed
    created_at = Column(DateTime, default=datetime.utcnow)
    completed_at = Column(DateTime, nullable=True)
    result = Column(JSON, nullable=True)


class Session(Base):
    """CWMP session tracking"""
    __tablename__ = 'sessions'
    
    id = Column(String(100), primary_key=True)
    device_id = Column(String(100))
    started_at = Column(DateTime, default=datetime.utcnow)
    ended_at = Column(DateTime, nullable=True)
    inform_events = Column(JSON)
    messages_exchanged = Column(Integer, default=0)


# Database setup
engine = create_engine('sqlite:///tr069_acs.db', echo=False)
SessionLocal = sessionmaker(bind=engine)


def init_db():
    """Initialize database tables"""
    Base.metadata.create_all(bind=engine)


def get_db():
    """Get database session"""
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
