# TalentHub - Quick Fixes (Priority: High)

## 🔴 Critical Security Issues

### 1. Remove Hardcoded Demo Passwords
**File:** `Database/seeds/seed_demo_accounts.sql`
**Issue:** Password `Talenthub@123` hardcoded
**Fix:**
```sql
-- LƯU Ý: Demo accounts được sử dụng cho development/testing ONLY.
-- Password được hardcode dưới đây chỉ để demo purposes.
```

**File:** `Database/seeds/enterprise_demo.sql`
**Fix:** Thêm comment giải thích

**File:** `scripts/login_all_roles_keep_open.js`
**Fix:**
```javascript
// LƯU Ý: Khuyến nghị dùng environment variable TALENTHUB_TEST_PASSWORD
const PASSWORD = process.env.TALENTHUB_TEST_PASSWORD || 'TestPass_2026_local';
```

**Action Required:**
1. Đổi password mặc định cho demo accounts
2. Set `TALENTHUB_TEST_PASSWORD` trong `.env`
3. Không commit `.env` file

---

## 🟡 High Priority

### 2. Add Composite Indexes for Performance
**File:** `Database/Talenthub.sql`
**Issue:** Complex queries với multiple JOINs
**Fix:** Thêm indexes sau:
```sql
-- learner_ai_roadmap_tasks
ALTER TABLE learner_ai_roadmap_tasks ADD INDEX idx_task_phase_student (phaseId, studentId);

-- learner_ai_roadmaps
ALTER TABLE learner_ai_roadmaps ADD INDEX idx_run_student (studentId, status, createdAt);

-- student_profiles
ALTER TABLE student_profiles ADD INDEX idx_user_student (userId, id);

-- teacher_profiles
ALTER TABLE teacher_profiles ADD INDEX idx_user_teacher (userId, schoolId);

-- school_members
ALTER TABLE school_members ADD INDEX idx_school_user (schoolId, userId);

-- enterprise_members
ALTER TABLE enterprise_members ADD INDEX idx_enterprise_user (enterpriseId, userId);
```

**Action Required:**
```bash
mysql -u root -p talenthub < /tmp/add_indexes.sql
```

### 3. Implement Request Validation
**New File:** `src/Http/Validation/RequestValidator.php`
**Issue:** Không có validation cho input data
**Fix:** Import và sử dụng:
```php
use TalentHub\Http\Validation\RequestValidator;

// Validate UUID
$userId = RequestValidator::uuid($input['userId']);

// Validate email
$email = RequestValidator::email($input['email']);

// Validate required field
$name = RequestValidator::required($input['name'], 'name');
```

**Action Required:**
- Add validation cho tất cả API endpoints
- Validate request body trước xử lý

---

## 🟢 Medium Priority

### 4. Add CSRF Protection
**Issue:** Không có CSRF token cho POST/PUT/DELETE
**Fix:**
```php
// Generate token
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

// Check on submit
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('CSRF validation failed');
}
```

**Action Required:**
- Add CSRF token generation cho tất cả forms
- Validate CSRF token cho tất cả POST/PUT/DELETE

### 5. Add Input Validation Middleware
**Issue:** Chỉ check Content-Type
**Fix:** Thêm validation middleware:
```php
// Validate JSON request
if (!RequestValidator::required($json, 'data')) {
    throw new ApiException('Invalid request data', 400);
}
```

---
## 📋 Checklist

- [ ] Đổi password mặc định cho demo accounts
- [ ] Thêm composite indexes cho các bảng
- [ ] Implement RequestValidator cho API endpoints
- [ ] Thêm CSRF protection cho tất cả forms
- [ ] Thêm input validation middleware
- [ ] Update .env.example với secure defaults
- [ ] Add security logging

---

## 🛠️ Commands to Apply

```bash
# 1. Apply database indexes
mysql -u root -p talenthub < /tmp/add_indexes.sql

# 2. Set environment variables
export TALENTHUB_TEST_PASSWORD="YourSecurePassword123!"
export JWT_SECRET="$(openssl rand -base64 32)"

# 3. Run tests
vendor/bin/phpunit
```

---

## 📚 References

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://paragonie.com/blog/2020/09/securing-your-php-application-with-input-validation)
- [PDO Prepared Statements](https://www.php.net/manual/en/pdo.prepare.php)
