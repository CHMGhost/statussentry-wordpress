#!/bin/bash

# Setup script for WordPress Docker environment

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Setting up WordPress Docker environment...${NC}"

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Docker is not installed. Please install Docker first.${NC}"
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}Docker Compose is not installed. Please install Docker Compose first.${NC}"
    exit 1
fi

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    echo -e "${YELLOW}Creating .env file from .env.example...${NC}"
    cp .env.example .env
    echo -e "${GREEN}.env file created.${NC}"
else
    echo -e "${YELLOW}.env file already exists.${NC}"
fi

# Start Docker Compose
echo -e "${YELLOW}Starting Docker Compose...${NC}"
docker-compose up -d

# Check if WordPress is running
echo -e "${YELLOW}Checking if WordPress is running...${NC}"
if curl -s http://localhost:8000 | grep -q "WordPress"; then
    echo -e "${GREEN}WordPress is running!${NC}"
    echo -e "${GREEN}WordPress URL: http://localhost:8000${NC}"
    echo -e "${GREEN}phpMyAdmin URL: http://localhost:8080${NC}"
else
    echo -e "${RED}WordPress is not running. Please check the logs with 'docker-compose logs'.${NC}"
fi

echo -e "${GREEN}Setup complete!${NC}"
