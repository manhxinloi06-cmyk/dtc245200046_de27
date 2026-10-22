# Đề 27 – Hệ thống Điểm danh Sinh viên / Student Attendance

Stack: PHP 8.3/Apache chạy non-root trên cổng nội bộ 8080 + MySQL 8 + phpMyAdmin + Nginx reverse proxy + Prometheus + Grafana + Loki + Promtail + cAdvisor + Nginx Prometheus Exporter.

## 1. Yêu cầu
- Docker Desktop có Docker Compose.
- Git.
- Trình duyệt web.

## 2. Chạy dự án
1. Giải nén thư mục dự án.
2. Mở terminal tại thư mục có `docker-compose.yml`.
3. Chạy:
   ```bash
   docker compose up -d --build
   docker compose ps
   docker compose logs --tail=100
   ```
4. Mở các địa chỉ:
   - Website (HTTP): http://localhost:8088
   - Website (HTTPS): https://localhost
   - phpMyAdmin: http://localhost:8081
   - Prometheus: http://localhost:9090
   - Grafana: http://localhost:3000
   - Loki readiness: http://localhost:3100/ready

Grafana mặc định dùng tài khoản `admin`; mật khẩu lấy từ `GRAFANA_ADMIN_PASSWORD` trong `.env`.
phpMyAdmin đăng nhập bằng user `attendance_app` và mật khẩu `DB_APP_PASSWORD` trong `.env`, hoặc user `root` với `DB_ROOT_PASSWORD`.

## 3. Chức năng
- Đăng nhập/đăng xuất quản trị viên.
- Dashboard thống kê tổng số sinh viên, số lượng sinh viên có mặt hôm nay.
- Danh sách sinh viên theo lớp.
- Điểm danh sinh viên vào lớp và ra về; mỗi sinh viên tối đa một bản ghi mỗi ngày.
- Lịch sử điểm danh sinh viên.
- Tài khoản quản trị viên:
  - Username: `admin`
  - Password: `123456`
- Có dữ liệu sinh viên mẫu để trình bày; không dùng dữ liệu demo trong môi trường thực tế.

## 4. Kiểm tra từng tiêu chí
### Ứng dụng + Database
- Vào website, đăng nhập.
- Điểm danh sinh viên vào/ra, kiểm tra lịch sử.
- Vào phpMyAdmin, chọn database `attendance_db`, kiểm tra bảng `users`, `people`, `attendance`.

### Nginx Reverse Proxy
```bash
curl.exe -I http://localhost:8088
```
Kiểm tra các header `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.

### Prometheus + Grafana
- Mở Prometheus > Status > Targets.
- Các target kỳ vọng: `prometheus`, `cadvisor`, `nginx-exporter`.
- Grafana > Dashboards > Attendance Infrastructure.
- Metrics hữu ích: `up`, `container_cpu_usage_seconds_total`, `container_memory_working_set_bytes`.

### Loki + Promtail
Trong Grafana > Explore, chọn datasource Loki và thử:
```logql
{job="docker", container=~".*nginx.*"} (Giám sát web server Nginx)
{job="docker"} |= "POST" (Giám sát thao tác gửi yêu cầu điểm danh / đăng nhập)
{job="docker", stream="stderr"} (Kiểm tra luồng cảnh báo/lỗi hệ thống)
```
Log container có thể cần một thời gian ngắn để xuất hiện. Promtail cần quyền đọc Docker log trên host; đây là cấu hình lab, không nên đưa lên máy chủ production nguyên trạng.

## 5. Ba commit bắt buộc
Sau khi kiểm tra và chụp ảnh minh chứng từng giai đoạn:
```bash
git init
git branch -M main
git add .
git commit -m "Commit khởi tạo: ứng dụng điểm danh và cơ sở dữ liệu"
```
Sau khi cấu hình Nginx:
```bash
git add .
git commit -m "Commit 1: cấu hình Nginx reverse proxy và các header bảo mật"
```
Sau khi thêm monitoring và logging:
```bash
git add .
git commit -m "Commit 2: thêm Prometheus Grafana Loki và Promtail"
```
Để đáp ứng đúng yêu cầu chung có ba commit được đặt tên rõ ràng, commit phần hardening/báo cáo:
```bash
git add .
git commit -m "Commit 3: gia cố bảo mật container và tài liệu triển khai"
```
Tạo repository GitHub với tên theo mã sinh viên, ví dụ `DE27_<MA_SO_SV>`, rồi thêm remote:
```bash
git remote add origin https://github.com/<GITHUB_USERNAME>/<REPOSITORY>.git
git push -u origin main
```
Thay các placeholder bằng thông tin của bạn. Không commit file `.env`.

## 6. LogQL mẫu
```logql
• {job="docker", container=~\".*nginx.*\"} (Giám sát web server Nginx)
• {job="docker"} |= "POST" (Giám sát thao tác gửi yêu cầu điểm danh / đăng nhập)
• {job="docker", stream=\"stderr\"} (Kiểm tra luồng cảnh báo/lỗi hệ thống)
```

## 7. Tắt hệ thống
```bash
docker compose down
```
Xóa cả dữ liệu database (không thể khôi phục nếu chưa backup):
```bash
docker compose down -v
```

## 8. Lưu ý bảo mật
- Đổi mọi mật khẩu trong `.env`, không dùng mật khẩu demo khi trình diễn công khai.
- Không đưa `.env`, dữ liệu thật, khóa bí mật vào GitHub.
- phpMyAdmin, Prometheus, Grafana và Loki đang được publish port để phục vụ bài lab; chỉ dùng trên máy cá nhân/mạng tin cậy. Production cần VPN/SSO/TLS và ACL.
- HTTPS tự ký được cấu hình ở cổng 443; trình duyệt có thể cảnh báo chứng chỉ không được CA tin cậy.
