#!/bin/bash

# WordPress Plugin Build Script
# Creates optimized distribution ZIP with vendor exclusion
#
# Usage: ./build-plugin.sh [version]
# If version not provided, auto-detects from plugin header

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[BUILD]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="poker-tournament-import"

# Check if plugin directory exists
if [ ! -d "$PLUGIN_DIR" ]; then
    print_error "Plugin directory '$PLUGIN_DIR' not found!"
    exit 1
fi

print_status "Starting WordPress plugin build process..."

# Detect version
if [ -n "$1" ]; then
    VERSION="$1"
    print_info "Using provided version: $VERSION"
else
    # Auto-detect version from main plugin file
    VERSION=$(grep -o "Version: [0-9]\+\.[0-9]\+\.[0-9]\+" "$PLUGIN_DIR/poker-tournament-import.php" | head -1 | grep -o "[0-9]\+\.[0-9]\+\.[0-9]\+")
    if [ -z "$VERSION" ]; then
        print_error "Could not auto-detect version. Please provide version as argument."
        exit 1
    fi
    print_info "Auto-detected version: $VERSION"
fi

# Validate version format
if [[ ! $VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9]+)?$ ]]; then
    print_error "Invalid version format: $VERSION (should be X.Y.Z or X.Y.Z-betaN)"
    exit 1
fi

# Output filename
ZIP_FILENAME="poker-tournament-import-v$VERSION.zip"
BUILD_DIR="build-$VERSION"

print_status "Building version $VERSION..."

# Clean previous builds
print_status "Cleaning previous builds..."
rm -rf "$BUILD_DIR"
rm -f "$ZIP_FILENAME"

# Create build directory
mkdir -p "$BUILD_DIR"

# Copy plugin files to build directory
print_status "Copying plugin files..."
cp -r "$PLUGIN_DIR" "$BUILD_DIR/"

# Remove excluded files from build directory
print_status "Applying exclusions from .distignore..."

if [ -f ".distignore" ]; then
    while IFS= read -r pattern; do
        # Skip empty lines and comments
        [[ -z "$pattern" || "$pattern" == \#* ]] && continue

        # Convert pattern to find command
        if [[ "$pattern" == */ ]]; then
            # Directory pattern
            find_pattern="$pattern"
        else
            # File pattern - remove leading *
            find_pattern="${pattern#*}"
        fi

        # Remove files/directories
        if [ -d "$BUILD_DIR/$PLUGIN_DIR/$find_pattern" ]; then
            rm -rf "$BUILD_DIR/$PLUGIN_DIR/$find_pattern"
            print_info "Removed: $find_pattern"
        else
            find "$BUILD_DIR/$PLUGIN_DIR" -name "$find_pattern" -type f -delete 2>/dev/null || true
        fi
    done < ".distignore"
else
    print_warning "No .distignore file found. Using default exclusions."
    # Default exclusions
    rm -rf "$BUILD_DIR/$PLUGIN_DIR/vendor"
    rm -f "$BUILD_DIR/$PLUGIN_DIR/composer.json"
    rm -f "$BUILD_DIR/$PLUGIN_DIR/composer.lock"
fi

# Validate essential files
print_status "Validating essential files..."

essential_files=(
    "poker-tournament-import.php"
)

for file in "${essential_files[@]}"; do
    if [ ! -f "$BUILD_DIR/$PLUGIN_DIR/$file" ]; then
        print_error "Essential file missing: $file"
        exit 1
    fi
done

print_info "All essential files validated successfully."

# Calculate sizes
original_size=$(du -sh "$PLUGIN_DIR" | cut -f1)
build_size=$(du -sh "$BUILD_DIR/$PLUGIN_DIR" | cut -f1)

print_status "Size comparison:"
print_info "  Original size: $original_size"
print_info "  Build size: $build_size"

# Create ZIP file
print_status "Creating distribution ZIP..."
cd "$BUILD_DIR"
zip -r "$ZIP_FILENAME" "$PLUGIN_DIR/" >/dev/null

if [ $? -eq 0 ]; then
    zip_size=$(du -sh "$ZIP_FILENAME" | cut -f1)
    print_status "✅ Distribution ZIP created successfully!"
    print_info "  ZIP file: $ZIP_FILENAME ($zip_size)"

    # Move ZIP to parent directory
    mv "$ZIP_FILENAME" "../"

    print_status "✅ Build completed successfully!"
    print_info "Distribution file ready: $ZIP_FILENAME"

    # Cleanup build directory
    cd ..
    rm -rf "$BUILD_DIR"

    # Summary
    print_status "Build Summary:"
    print_info "  Plugin: poker-tournament-import"
    print_info "  Version: $VERSION"
    print_info "  Original: $original_size"
    print_info "  Final: $zip_size"
    print_info "  Location: $(pwd)/$ZIP_FILENAME"

else
    print_error "❌ Failed to create ZIP file!"
    exit 1
fi

print_status "Build process completed! 🎉"