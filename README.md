# WordPress Docker Development Environment

This is a simple Docker Compose setup for WordPress development.

## Requirements

- Docker
- Docker Compose

## Getting Started

1. Clone this repository
2. Create a `.env` file based on `.env.example`
3. Start the environment:

```bash
docker-compose up -d
```

4. Access WordPress at http://localhost:8000
5. Access phpMyAdmin at http://localhost:8080

## Configuration

You can customize the environment by editing the `.env` file. See `.env.example` for available options.

## Stopping the Environment

```bash
docker-compose down
```

To remove all data (including the database):

```bash
docker-compose down -v
```
