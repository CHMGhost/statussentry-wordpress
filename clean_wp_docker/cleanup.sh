#!/bin/bash

# Cleanup script for WordPress Docker environment

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Stopping Docker Compose...${NC}"
docker-compose down

# Ask if user wants to remove volumes
read -p "Do you want to remove all data (including the database)? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Removing volumes...${NC}"
    docker-compose down -v
    echo -e "${GREEN}Volumes removed.${NC}"
fi

echo -e "${GREEN}Cleanup complete!${NC}"
