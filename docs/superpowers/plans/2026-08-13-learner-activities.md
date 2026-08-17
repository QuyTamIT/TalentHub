# Learner Activities Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Hoàn thiện frontend mock-first cho hoạt động trải nghiệm và khóa luồng Holland.

**Architecture:** PHP provider phát catalog/history; JavaScript domain module xử lý status, chống trùng, xung đột và LocalStorage; PHP routes render catalog/detail/my activities. Hợp đồng dữ liệu được giữ gần schema hiện tại.

**Tech Stack:** PHP 8.3, JavaScript, LocalStorage, Node test runner, CSS learner.

## Tasks

1. Viết failing test và provider `activity-data.php` cho catalog/registration history.
2. Viết failing Node test và `learner-activities.js` cho status rules, conflict, storage, cancel, feedback.
3. Viết failing render test rồi thêm `activity-detail.php`, `my-activities.php`, cập nhật `activities.php` và route whitelist.
4. Nối DOM interactions, LocalStorage và check-in CTA.
5. Khóa Holland labels/latest-result block trên Discover.
6. Thêm CSS responsive; chạy PHP lint, Node/PHP tests, HTTP smoke, diff check và scope guard.
