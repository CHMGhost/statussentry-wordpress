# Contributing to Status Sentry WP

Thank you for considering contributing to Status Sentry WP! This document outlines the process for contributing to the project.

## Code of Conduct

By participating in this project, you agree to abide by our Code of Conduct. Please read it before contributing.

## How Can I Contribute?

### Reporting Bugs

Before creating a bug report:

1. Check the [issue tracker](https://github.com/status-sentry/status-sentry-wp/issues) to see if the problem has already been reported
2. If you're unable to find an open issue addressing the problem, create a new one

When creating a bug report, please include as much detail as possible:

- A clear and descriptive title
- Steps to reproduce the issue
- Expected behavior
- Actual behavior
- Screenshots if applicable
- Your WordPress version
- Your PHP version
- Your MySQL version
- List of active plugins
- Any relevant error messages or logs

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion:

1. Use a clear and descriptive title
2. Provide a detailed description of the suggested enhancement
3. Explain why this enhancement would be useful
4. Include any relevant examples or mockups

### Pull Requests

1. Fork the repository
2. Create a new branch for your feature or bugfix
3. Make your changes
4. Run the tests to ensure your changes don't break existing functionality
5. Submit a pull request

## Development Workflow

### Setting Up the Development Environment

1. Clone your fork of the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Set up a local WordPress development environment
4. Symlink or copy the plugin directory to your WordPress plugins directory

### Coding Standards

This project follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/). Please ensure your code adheres to these standards.

You can check your code using PHP_CodeSniffer:

```bash
composer run phpcs
```

And automatically fix many issues with:

```bash
composer run phpcbf
```

### Testing

Before submitting a pull request, please run the tests to ensure your changes don't break existing functionality:

```bash
composer run test
```

## Documentation

Documentation is a crucial part of this project. Please update the documentation when you make changes to the code.

### Inline Documentation

All classes, methods, and functions should be documented using PHPDoc comments. Example:

```php
/**
 * Process events from the queue.
 *
 * @since    1.0.0
 * @param    int    $batch_size    The number of events to process in a batch.
 * @return   int                   The number of events processed.
 */
public function process_events($batch_size = 100) {
    // Implementation
}
```

### README and Other Documentation

If your changes affect how users interact with the plugin, please update the README.md file accordingly.

## Release Process

1. Update the version number in:
   - `status-sentry-wp.php`
   - `README.md`
   - Any other relevant files
2. Update the changelog in `README.md`
3. Create a new release on GitHub with release notes

## Questions?

If you have any questions about contributing, please open an issue or contact the project maintainers.

Thank you for contributing to Status Sentry WP!
