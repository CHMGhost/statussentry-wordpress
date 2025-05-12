#!/bin/bash

# Script to package the Status Sentry WordPress plugin into a zip file
# This script should be run from the root of the repository

# Set variables
PLUGIN_DIR="status-sentry"
ZIP_NAME="status-sentry-wp.zip"
VERSION=$(grep "define('STATUS_SENTRY_VERSION'" "$PLUGIN_DIR/status-sentry-wp.php" | cut -d "'" -f 4)

echo "Packaging Status Sentry WordPress plugin version $VERSION..."

# Check if the plugin directory exists
if [ ! -d "$PLUGIN_DIR" ]; then
    echo "Error: Plugin directory '$PLUGIN_DIR' not found!"
    exit 1
fi

# Remove any existing zip file
if [ -f "$ZIP_NAME" ]; then
    echo "Removing existing zip file..."
    rm "$ZIP_NAME"
fi

# Create the zip file
echo "Creating zip file..."
zip -r "$ZIP_NAME" "$PLUGIN_DIR" -x "*.git*" -x "*.DS_Store" -x "*__MACOSX*"

# Check if the zip was created successfully
if [ -f "$ZIP_NAME" ]; then
    echo "Successfully created $ZIP_NAME (version $VERSION)"
    echo "File size: $(du -h "$ZIP_NAME" | cut -f1)"
else
    echo "Error: Failed to create zip file!"
    exit 1
fi

echo "Done!"
