SCSM2223 Chapter 12 - Books API Secure

Project folder name: books-api-secure

Demo users:
  admin@books.test  / password
  member@books.test / password

Backend setup:
  cd C:\laragon\www\books-api-secure
  composer install
  composer dump-autoload
  mysql -u root < sql\schema.sql
  php -S localhost:8000 -t public

Frontend setup:
  cd C:\laragon\www\books-api-secure\frontend
  npm install
  npm run dev

Open frontend:
  http://localhost:5173/

Main Lab 12 hardening added:
  - Validator class
  - JSON_HEX safe output
  - SecurityHeaders middleware
  - RateLimit middleware on /auth/login
  - CORS origin allow-list
  - books.created_by ownership field
  - IDOR protection for PUT /api/books/{id}
  - audit_log table and audit records

If Composer blocks firebase/php-jwt because of security audit:
  composer config audit.block-insecure false
  composer update
  composer dump-autoload
