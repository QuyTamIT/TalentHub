# Thiết Kế Chuẩn Hóa Bố Cục & Lề Viền Khu Vực Chứng Chỉ & Huy Hiệu (Learner Credentials & Badges)

**Ngày lập**: 2026-09-07  
**Trạng thái**: Đã duyệt thiết kế  
**Mục tiêu**: Khắc phục triệt để tình trạng khối chứng chỉ và huy hiệu bị dính sát lề trên cả trang Hồ sơ năng lực (`profile.php`), Trang Huy hiệu (`badges.php`) và Bảng tin (`index.php`), tạo độ thở và nhịp điệu thị giác chuyên nghiệp, hài hòa theo chuẩn Card Dashboard.

---

## 1. Vấn đề Thực tế & Phân Tích Kỹ Thuật

- **Thực trạng**:
  - Khối `.learner-school-credential-section` được gán class `.learner-card` nhưng không có thuộc tính `padding` nào (mặc định bằng `0px`).
  - Toàn bộ nội dung gồm thanh tiêu đề (`.learner-school-credential-heading`), tiêu đề phụ (eyebrow), đoạn mô tả và nút điều hướng bị ép sát vào viền mép trên và hai bên lề.
  - Lưới chứa các thẻ chứng chỉ Diploma và huy hiệu Medal (`.learner-school-credential-grid`) chạm sát viền ngoài của Card, thiếu khoảng đệm ngăn cách.
  - Bên trong thẻ Diploma (`.learner-credential-card--certificate`) và Medal (`.learner-credential-card--badge`), khoảng đệm và kích thước chưa được tối ưu, khiến văn bản tên chứng chỉ và thông tin cấp phát có cảm giác chật chội.

---

## 2. Giải Pháp Thiết Kế & Quy Chuẩn Giao Diện

### 2.1. Khung Thẻ Bao Ngoài (`.learner-school-credential-section`)
- **Khoảng đệm nội bộ (Padding)**:
  - Desktop (> 992px): `padding: 28px 32px;`
  - Tablet (768px - 991px): `padding: 24px 24px;`
  - Mobile (< 768px): `padding: 20px 16px;`
- **Khoảng cách nhịp thở**:
  - `gap: 24px;` giữa Header và Lưới chứng chỉ/huy hiệu.
  - Bo góc mềm mại: `border-radius: var(--radius-md, 16px);`.
  - Nền và viền: `background: var(--surface); border: 1px solid var(--border); box-shadow: var(--shadow-sm);`.

### 2.2. Khu Vực Tiêu Đề Khối (`.learner-school-credential-heading`)
- **Dòng Eyebrow (`.learner-school-credential-heading__eyebrow`)**:
  - Chuyển thành dạng badge/pill hiện đại:
    * `display: inline-flex; align-items: center; gap: 7px;`
    * `padding: 4px 12px;`
    * `border-radius: 999px;`
    * `background: color-mix(in srgb, var(--primary) 9%, transparent);`
    * `color: var(--primary); font-size: 0.76rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;`
- **Tiêu đề khối (`h2`)**:
  - `margin: 8px 0 6px;`
  - `font-size: clamp(1.2rem, 2.2vw, 1.45rem); font-weight: 750; color: var(--text-primary); line-height: 1.25;`
- **Đoạn mô tả (`p`)**:
  - `max-width: 760px; margin: 0; color: var(--text-secondary); line-height: 1.55; font-size: 0.88rem;`
- **Nút liên kết điều hướng (`> a`)**:
  - Dạng subtle pill button:
    * `display: inline-flex; align-items: center; gap: 8px;`
    * `padding: 7px 16px; border-radius: 999px;`
    * `background: color-mix(in srgb, var(--primary) 8%, var(--surface));`
    * `border: 1px solid color-mix(in srgb, var(--primary) 20%, transparent);`
    * `color: var(--primary); font-weight: 700; font-size: 0.85rem; text-decoration: none;`
    * Hover: `background: var(--primary); color: #fff; transform: translateY(-1px);`

### 2.3. Lưới Hiển Thị (`.learner-school-credential-grid`)
- **Khoảng cách giữa các thẻ con**: `gap: 20px;`
- Cột chứng chỉ Diploma (`.learner-school-credential-grid--certificates`): `grid-template-columns: repeat(3, minmax(0, 1fr));` (trên màn hình lớn), tự động co giãn 2 cột ở tablet và 1 cột ở mobile.
- Cột huy hiệu Medal (`.learner-school-credential-grid--badges`): `grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));` cân đối trên toàn bộ chiều rộng.

### 2.4. Tối Ưu Thẻ Chứng Chỉ Diploma (`.learner-credential-card--certificate`)
- Padding thẻ ngoài: `10px`.
- Khung văn bằng nội bộ (`.learner-credential-card__diploma-frame`):
  - `padding: 20px 22px 18px;`
  - Viền kép diploma chuẩn chỉ, bo góc mượt.
- Logo nón cử nhân & vòng nguyệt quế căn giữa trang nhã.
- Nhãn trạng thái ("ĐÃ ĐẠT" / "ĐANG TIẾN HÀNH" / "CHƯA MỞ KHÓA") với 2 đường chỉ ngăn cách sang trọng.
- Tên chứng chỉ (`h3`):
  - `margin: 12px 0 8px; font-size: 1.05rem; font-weight: 750; line-height: 1.35; text-align: center;`
- Đáy thẻ: Con dấu "Đã xác minh" hoặc chỉ số hoàn thành cấp độ hiển thị rõ ràng, không bị cộc.

### 2.5. Tối Ưu Thẻ Huy Hiệu Medal (`.learner-credential-card--badge`)
- Padding thẻ: `22px 18px 20px;`
- Vòng tiến độ và huy hiệu: đường kính cân đối, màu sắc rõ ràng theo trạng thái.
- Tiêu đề tên huy hiệu (`h3`): căn giữa với khoảng đệm an toàn 2 bên.
- Thông tin tiêu chí / con dấu xác nhận ở đáy thẻ thoáng đãng.

---

## 3. Kế Hoạch Xác Minh (Verification)
- Kiểm tra trực tiếp hiển thị trên các màn hình và thiết bị:
  1. `app/learner/profile.php`: Khối "Chứng chỉ do trường cấp".
  2. `app/learner/badges.php`: Khối "Huy hiệu chính thức của trường" và "Chứng chỉ chính thức của trường".
  3. `app/learner/index.php`: Khối "Thành tích do trường cấp".
- Kiểm tra tính tương thích Responsive (Desktop, Tablet, Mobile) không bị tràn ngang hoặc dính lề.
