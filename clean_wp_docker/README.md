# WordPress Docker Development Environment

This is a simple Docker Compose setup for WordPress development.

## Requirements

- Docker
- Docker Compose

## Getting Started

### Using the Setup Script

1. Clone this repository
2. Run the setup script:

```bash
./setup.sh
```

This will:
- Create a `.env` file from `.env.example` if it doesn't exist
- Start the Docker containers
- Verify that WordPress is running

### Manual Setup

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

### Using the Cleanup Script

```bash
./cleanup.sh
```

This will:
- Stop the Docker containers
- Optionally remove all data (including the database)

### Manual Cleanup

```bash
# Stop containers
docker-compose down

# Remove all data (including the database)
docker-compose down -v
```

## Directory Structure

When the environment is running, WordPress files will be available in the `wordpress` directory, which is mounted as a volume in the Docker container.

## Plugin Development

To develop a WordPress plugin:

1. Create a directory for your plugin in `wordpress/wp-content/plugins/your-plugin-name`
2. Start developing your plugin
3. Your changes will be immediately available in the WordPress admin
