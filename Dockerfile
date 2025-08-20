FROM php:8.2-apache

# ✨ Cài đặt các extension cần thiết cho PostgreSQL và MySQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pgsql mysqli pdo pdo_mysql

# 🔥 Bật mod_rewrite để hỗ trợ .htaccess
RUN a2enmod rewrite

# ✅ Cho phép sử dụng .htaccess trong thư mục gốc
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# 📁 Copy source vào container
COPY . /var/www/html/

# 🧾 Thiết lập quyền để Apache có thể truy cập source và file upload
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# 🔐 (Tùy chọn) Tăng bảo mật: Không cho list directory
RUN echo "Options -Indexes" >> /etc/apache2/apache2.conf

# 🔥 Tùy chọn: Thêm .dockerignore để tránh copy file không cần thiết
# Gợi ý tạo file .dockerignore như sau:
# .git
# node_modules
# *.md

# 🌐 Khai báo cổng phục vụ
EXPOSE 80

# 🚀 Chạy Apache khi container khởi động
CMD ["apache2-foreground"]
