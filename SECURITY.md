# TalentHub Security Guide

Cách xử lý Escape và Input Sanitization trong TalentHub:

## 1. SQL Injection Prevention

### Phương pháp hiện tại:
- ✅ **PDO Prepared Statements** - Đã được sử dụng trong phần lớn codebase
- ✅ **Parameter Binding** - Cố định tham số, tránh injection SQL

### Example:
```php
// ✅ An toàn - Sử dụng prepared statements
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);

// ✅ An toàn - Named parameters
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);
```

### Cần cải thiện (ví dụ):
Union-based hoặc Error-based injection có thể tồn tại
trong query string query parameters.

## 2. XSS Prevention

### Phương pháp hiện tại:
- ✅ **htmlspecialchars()** - HTML escaping khi output
- ✅ **strip_tags()** - Xóa HTML tags khi validate input

### Example:
```php
// ✅ An toàn - Sanitize output
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// ✅ An toàn - Input sanitization trước khi store
$clean = strip_tags(trim($userInput));
```

### Cách dùng mới:
Add `RequestValidator::sanitize()` để chuẩn hóa input
trước khi lưu vào database:
```php
$clean = RequestValidator::sanitize($input); // Trim + strip tags
```

## 3. CSRF Protection

### Phương pháp hiện tại:
- ⚠️ **Phi có thể tái tạo - X-RANDOM-HEADER**
- ⚠️ **Session-based check** - Weak protection

### Cải thiện:
1. Sử dụng CSRF tokens cho tất cả POST/PUT/DELETE requests
2. Chuyển sang double-submit cookie pattern

```php
// 1. Generate token
$token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $token;

// 2. Check on form submit
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('CSRF validation failed');
}
```

## 4. Password Security

### Không nên làm:
- ❌ Hardcode passwords trong code base
- ❌ Store passwords plain text

### Nên làm:
- ✅ Password hashing với bcrypt
- ✅ Force password reset sau login lần đầu
- ✅ Complexity requirements

## 5. File Upload Security

### Controls cần thêm:
- File size limits (e.g., <= 5MB)
- MIME type validation
- Whitelist allowed extensions
- Store files non-executable location
- Generate unique filenames

## 6. Rate Limiting

### Controls cần thêm:
- API request throttling
- Login attempt limits
- Rate limit per IP and per user

## 7. Logging và Monitoring

### Cần thêm:
- Security event logging (failed auth, CSRF errors)
- Error tracking (Sentry, Flare)
- Anomaly detection alerts

---

## Cách Escape các loại dữ liệu:

| Data Type | Escape Method | Example |
|-----------|---------------|---------|
| SQL       | PDO Prepared Statements | `$stmt->execute([$param])` |
| HTML      | htmlspecialchars() | `htmlspecialchars($text)` |
| JavaScript| json_encode() | `json_encode($data)` |
| URL       | urlencode() | `urlencode($param)` |
| CSV       | Addcslashes() | `addcslashes($value)` |
ENVEOF
