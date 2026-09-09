# HUONG DAN BAN GIAO VA DONG BO KY THUAT - PHAN HE GIANG VIEN (TEACHER PORTAL)

Muc tieu:
Tach viec cham diem Rubric nang luc khoi Workshop QR. Cho phep Giang vien cham diem theo Lop hoc hoac Do an huong dan.
Ngay khi Giang vien bam "Cong bo danh gia" (status = published), phia Sinh vien se lap tuc nhan duoc thong bao va nhin thay day du bang diem Rubric, diem tong, xep loai va nhan xet cua Giang vien tai trang app/learner/evaluation.php va app/learner/talent-passport.php.

================================================================================
PHAN 1: NGUYEN TAC DONG BO DU LIEU GIUA GIANG VIEN VA SINH VIEN
================================================================================

1. Phia Sinh vien xem danh gia o dau?
- Trang 1: app/learner/evaluation.php (Trang chi tiet danh gia nang luc)
  + Hien thi: Ten Giang vien cham, Ngay cong bo, Ngu canh (Ten lop hoc hoac Ten do an).
  + Hien thi: Diem tong quan quy doi thang 10, Xep loai (Xuat sac, Tot, Kha, Can cai thien).
  + Hien thi: Bang chi tiet diem tung tieu chi Rubric (Chuyen mon, Thai do, Lam viec nhom, Thuyet trinh...).
  + Hien thi: Toan van loi nhan xet, gop y cua Giang vien.
- Trang 2: app/learner/talent-passport.php (Ho chieu nang luc)
  + Hien thi: Diem danh gia nang luc va loi nhan xet cua giang vien tren ho chieu.
- Thong bao: Chuong thong bao tu dong hien cham do thong bao ket qua moi kem link xem danh gia.

2. Dieu kien de Sinh vien nhin thay:
- Ban ghi trong bang `assessments` phai co: `status = 'published'` va `publishedAt IS NOT NULL`.
- Neu Giang vien luu o trang thai `status = 'draft'` (Ban nhap): Sinh vien hoan toan KHONG nhin thay (bao mat du lieu khi chua cham xong).

================================================================================
PHAN 2: CO SO DU LIEU CAN CHAY (DATABASE MIGRATION)
================================================================================

File migration: Database/migrations/learner/018_decouple_competency_assessments.php
(Chay bang lenh: php bin/learner-migrate.php up)

Cac cot can co trong bang `assessments`:
- `activityId`: CHAR(36) NULL (Cho phep null, khong bat buoc phai co workshop).
- `classId`: CHAR(36) NULL (Luu id lop hoc neu cham theo lop).
- `projectId`: CHAR(36) NULL (Luu id do an neu cham theo do an).
- `overallScore`: DECIMAL(5,2) NULL (Diem tong tu 0 den 100).
- `comment`: VARCHAR(1000) NULL (Nhan xet cua giang vien).
- `status`: VARCHAR(50) NOT NULL ('draft' hoac 'published').
- `publishedAt`: DATETIME NULL (Thoi diem giang vien bam Cong bo).
- `version`: INT NOT NULL DEFAULT 1.

Bang chi tiet diem Rubric `assessment_scores`:
- `id`: CHAR(36) NOT NULL PRIMARY KEY.
- `assessmentId`: CHAR(36) NOT NULL (Tro den assessments.id).
- `criteriaId`: CHAR(36) NOT NULL (Tro den assessment_criteria.id).
- `score`: DECIMAL(5,2) NOT NULL (Diem cua tieu chi).

================================================================================
PHAN 3: CHI TIET CAC FILE PHIA GIANG VIEN CAN CHINH SUA
================================================================================

File 1: src/Modules/Teacher/Repository/TeacherGradingRepository.php
Can bo sung 4 ham truy van:
1. classes(string $teacherId): array
   - Lay danh sach lop hoc thuoc truong cua giang vien (id, name, code, studentCount).
2. projects(string $teacherId): array
   - Lay danh sach do an ma giang vien duoc phan cong lam mentor (id, title, category, status, memberCount).
3. studentsForClass(string $teacherId, string $classId, string $search = ''): array
   - Lay danh sach sinh vien trong lop kem thong tin danh gia hien tai (studentId, fullName, email, assessmentId, overallScore, comment, assessmentStatus, publishedAt).
4. studentsForProject(string $teacherId, string $projectId, string $search = ''): array
   - Lay danh sach sinh vien thanh vien do an kem thong tin danh gia hien tai.

File 2: src/Modules/Teacher/Service/TeacherGradingService.php
Can cap nhat ham pageData():
- Nhan them tham so: $mode ('class', 'project', 'activity') va $contextId (classId hoac projectId hoac activityId).
- Tra ve mang du lieu gom:
  + 'mode': Che do cham hien tai.
  + 'classes': Danh sach cac lop hoc.
  + 'projects': Danh sach cac do an.
  + 'activities': Danh sach workshop cu.
  + 'students': Danh sach sinh vien theo dung lop/do an duoc chon.
  + 'criteria': Danh sach tieu chi Rubric dang active ($this->repository->activeCriteria()).

File 3: app/teacher/assessments/index.php (Giao dien Cham diem Giang vien)
Can sua 3 phan:
1. Bo doan code chan cung redirect o dong 78:
   // Xoa doan: if (!isset($_GET['mode']) || $_GET['mode'] !== 'activity') { header(...); exit; }
   // Thay bang: $mode = $_GET['mode'] ?? 'class';
2. Bo sung thanh Tab chon che do cham:
   - Tab 1: Cham theo Lop hoc (?mode=class)
   - Tab 2: Cham theo Do an huong dan (?mode=project)
   - Tab 3: Cham theo Workshop / Su kien (?mode=activity)
3. Bo sung Dropdown chon cu the Lop hoac Do an:
   - Khi chon 1 Lop: Load danh sach sinh vien cua lop do.
   - Bang danh sach sinh vien co nut mo form cham Rubric gom:
     + Diem tung tieu chi Rubric (lay tu bang assessment_criteria).
     + Diem tong overallScore.
     + O nhap nhan xet comment.
     + Nut 1: "Luu nhap" (gui assessmentStatus = 'draft').
     + Nut 2: "Cong bo danh gia" (gui assessmentStatus = 'published').

================================================================================
PHAN 4: CAC FILE PHIA SINH VIEN DA SAN SANG DE HIEN THI
================================================================================

Phia Sinh vien da hoan thien san cac file sau, chi cho Giang vien cham va dang len la hien ngay:
1. app/learner/evaluation.php:
   - Tu dong doc cac danh gia co status = 'published'.
   - Tu dong hien thi nhan ngu canh "Danh gia theo lop hoc phan" hoac "Danh gia do an chuyen nganh".
   - Tu dong render radar / tien trinh Rubric, xep loai va nhan xet.
2. app/learner/talent-passport.php:
   - Tu dong lay diem overallScore va nhan xet gan nhat vao Ho chieu nang luc.
3. app/learner/data/Service/NotificationService.php:
   - Da co san event 'teacher_assessment_published' de gui thong bao den sinh vien.

================================================================================
PHAN 5: CANH BAO QUAN TRONG DE TRANH SAI SOT
================================================================================

1. KHONG nham lan voi trang app/teacher/grading.php:
   Trang grading.php la trang nhap diem tho cu (chi ghi 1 con so talentScore vao student_profiles), KHONG luu tieu chi Rubric va KHONG moc noi sang trang evaluation.php cua sinh vien.
   Moi thao tac cham Rubric phai thuc hien tai: app/teacher/assessments/index.php.

2. Tinh bat bien (Immutability):
   Ban ghi danh gia mot khi da o trang thai 'published' thi khong duoc phep sua truc tiep de dam bao tinh minh bach cho ho so cua sinh vien.

3. Kiem thu nghiem thu (5 buoc):
   - Buoc 1: Giang vien vao app/teacher/assessments/index.php?mode=class
   - Buoc 2: Chon lop BTEC-AI-2026A -> Chon sinh vien Le Quy Tam.
   - Buoc 3: Nhap diem Rubric, nhap nhan xet -> Bam "Luu nhap" -> Kiem tra ben Sinh vien: CHUA thay.
   - Buoc 4: Giang vien bam "Cong bo danh gia".
   - Buoc 5: Dang nhap Sinh vien Le Quy Tam -> Mo app/learner/evaluation.php: THAY ngay thong bao va toan bo bang diem Rubric kem loi nhan xet.

================================================================================
PHAN 6: TAP LENH TU DONG CHAY KIEM TRA & XU LY KHI CO LOI (SELF-TEST & DEBUG)
================================================================================

1. Cac lenh terminal tu dong chay kiem tra:

- Lenh 1: Kiem tra cu phap PHP (Syntax Lint) cua cac file vua sua:
  php -l src/Modules/Teacher/Repository/TeacherGradingRepository.php
  php -l src/Modules/Teacher/Service/TeacherGradingService.php
  php -l app/teacher/assessments/index.php
  (Ket qua phai bao: No syntax errors detected)

- Lenh 2: Kiem tra cau truc migration 018 decoupling:
  php tests/learner_competency_assessment_context_test.php
  (Ket qua phai bao: learner competency assessment context contract: PASS)

- Lenh 3: Kiem tra trang thai migration tren co so du lieu MySQL:
  php bin/learner-migrate.php status
  (Neu 018 bao PENDING, chay lenh sau de ap dung: php bin/learner-migrate.php up)

- Lenh 4: Kiem tra loi dinh dang va khoang trang git:
  git diff --check
  (Khong duoc co thong bao loi)

2. Huong dan tu dong xu ly khi gap loi thuong gap (Troubleshooting):

- Loi: "Foreign key constraint fails (fk_assessments_registration)"
  + Nguyen nhan: Chua ap dung migration 018 vao MySQL de go bo khoa ngoai cu bat buoc phai co activity_registrations.
  + Cach sua: Chay lenh `php bin/learner-migrate.php up` tren terminal de cap nhat schema bang `assessments`.

- Loi: "403 FORBIDDEN - Giao vien khong co quyen danh gia hoc vien trong lop/du an nay"
  + Nguyen nhan: He thong kiem tra bao mat nghiem ngat: Giang vien chi duoc cham sinh vien thuoc cung truong (cung schoolId) hoac sinh vien trong do an ma giang vien duoc chi dinh lam mentor (projects.mentorTeacherId).
  + Cach sua: Chon dung sinh vien trong danh sach ma he thong tra ve tu cac ham `studentsForClass` hoac `studentsForProject`, khong truyen ma studentId ngoai pham vi.

- Loi: "409 CONFLICT - Assessment version no longer matches" hoac "Published assessments are immutable"
  + Nguyen nhan: Ban ghi danh gia da duoc luu hoac cong bo o mot luot khac khien so phien ban (expectedVersion) khong khop.
  + Cach sua: Tai lai trang (F5) de lay du lieu va phien ban moi nhat tu co so du lieu roi thuc hien luu lai.

- Loi: "Phia Sinh vien mo trang evaluation.php hoac talent-passport.php khong thay danh gia"
  + Nguyen nhan: Ban ghi danh gia van dang duoc luu o trang thai `status = 'draft'` (Ban nhap) hoac truong `publishedAt` dang null.
  + Cach sua: Phia Giang vien can bam nut "Cong bo danh gia" de chuyen status thanh 'published' kem thoi gian cong bo `publishedAt = NOW()`.

- Loi: "Trang index.php bao loi boot hoac khong tai duoc du lieu"
  + Nguyen nhan: Ket noi co so du lieu chua san sang hoac bien moi truong thieu.
  + Cach sua: Kiem tra file `.env` (DB_HOST, DB_DATABASE, DB_USERNAME), kiem tra dich vu MySQL dang chay trong Laragon va kiem tra console log de xem chi tiet requestId.
