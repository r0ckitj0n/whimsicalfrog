#!/bin/bash

echo "🔧 Fixing file permissions on live server..."

# FTP configuration
FTP_HOST="whimsicalfrog.us"
FTP_USER="jongraves"
FTP_PASS="Palz2516"

echo "📡 Connecting to server and fixing permissions..."

# Use lftp to fix permissions
lftp -c "
set ftp:ssl-allow no
set ftp:ssl-force no
set ssl:verify-certificate no
open ftp://$FTP_USER:$FTP_PASS@$FTP_HOST
cd public_html

echo 'Setting directory permissions...'
chmod 755 images
chmod 755 images/products

echo 'Setting image file permissions...'
cd images/products
chmod 644 *.webp 2>/dev/null || echo 'No webp files to fix'
chmod 644 *.png 2>/dev/null || echo 'No png files to fix'
chmod 644 *.jpg 2>/dev/null || echo 'No jpg files to fix'
chmod 644 *.jpeg 2>/dev/null || echo 'No jpeg files to fix'

echo 'Setting root image permissions...'
cd ..
chmod 644 *.webp 2>/dev/null || echo 'No webp files in root images'
chmod 644 *.png 2>/dev/null || echo 'No png files in root images'
chmod 644 *.jpg 2>/dev/null || echo 'No jpg files in root images'

echo 'Setting other directory permissions...'
cd ..
chmod 755 css 2>/dev/null || echo 'No css directory'
chmod 755 js 2>/dev/null || echo 'No js directory'
chmod 755 api 2>/dev/null || echo 'No api directory'
chmod 755 sections 2>/dev/null || echo 'No sections directory'

echo 'Setting file permissions...'
chmod 644 *.php 2>/dev/null || echo 'No php files'
chmod 644 *.html 2>/dev/null || echo 'No html files'
chmod 644 css/*.css 2>/dev/null || echo 'No css files'
chmod 644 js/*.js 2>/dev/null || echo 'No js files'
chmod 644 api/*.php 2>/dev/null || echo 'No api php files'
chmod 644 sections/*.php 2>/dev/null || echo 'No section php files'

quit
"

echo "✅ Permissions fixed!"
echo "🔍 Testing image accessibility..."

# Test a few sample images
echo "Testing TS002A.webp..."
curl -I "https://whimsicalfrog.us/images/products/TS002A.webp" 2>/dev/null | head -1

echo "Testing a PNG file..."
curl -I "https://whimsicalfrog.us/images/products/AW001A.png" 2>/dev/null | head -1

echo "🎉 Permission fix complete!" 