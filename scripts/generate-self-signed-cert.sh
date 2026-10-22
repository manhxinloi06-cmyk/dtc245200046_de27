#!/bin/sh
set -eu
mkdir -p nginx/certs
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout nginx/certs/localhost.key \
  -out nginx/certs/localhost.crt \
  -subj "/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"
chmod 600 nginx/certs/localhost.key
echo "Đã tạo chứng chỉ tự ký tại nginx/certs/. Trình duyệt sẽ cảnh báo vì đây không phải CA công khai."
