(function initLearnerCheckin(global) {
  'use strict';

  const createFrameDecoder = ({ BarcodeDetector, jsQR, canvasFactory } = {}) => {
    if (typeof BarcodeDetector === 'function') return new BarcodeDetector({ formats: ['qr_code'] });
    if (typeof jsQR !== 'function' || typeof canvasFactory !== 'function') return null;
    const canvas = canvasFactory();
    const context = canvas && typeof canvas.getContext === 'function'
      ? canvas.getContext('2d', { willReadFrequently: true })
      : null;
    if (!canvas || !context) return null;
    return {
      async detect(video) {
        const width = Number(video && video.videoWidth) || 0;
        const height = Number(video && video.videoHeight) || 0;
        if (width < 1 || height < 1) return [];
        canvas.width = width;
        canvas.height = height;
        context.drawImage(video, 0, 0, width, height);
        const image = context.getImageData(0, 0, width, height);
        const result = jsQR(image.data, image.width, image.height, { inversionAttempts: 'attemptBoth' });
        return result && result.data ? [{ rawValue: result.data }] : [];
      },
    };
  };

  const bootNode = typeof document !== 'undefined' ? document.getElementById('learner-checkin-boot') : null;
  let boot = {};
  try { boot = bootNode ? JSON.parse(bootNode.textContent || '{}') : {}; } catch { boot = {}; }

  const api = global.TalentHubLearnerApi && global.TalentHubLearnerApi.createLearnerApiClient
    ? global.TalentHubLearnerApi.createLearnerApiClient({ baseUrl: boot.apiBase || '/app/learner/api/v1', csrfToken: boot.csrfToken || '' })
    : null;
  const state = { stream: null, submitting: false, frameId: 0, detector: null, decoding: false, cameraGeneration: 0, cameraPending: false };

  const video = document.querySelector('[data-camera-video]');
  const placeholder = document.querySelector('[data-camera-placeholder]');
  const startButton = document.querySelector('[data-camera-start]');
  const stopButton = document.querySelector('[data-camera-stop]');
  const form = document.querySelector('[data-manual-form]');
  const tokenField = document.querySelector('[data-manual-token]');
  const feedback = document.querySelector('[data-checkin-feedback]');
  const apiState = document.querySelector('[data-api-state]');
  const submitButton = document.querySelector('[data-submit-checkin]');
  const resetButton = document.querySelector('[data-reset-checkin]');
  const historyList = document.querySelector('[data-checkin-history]');
  const historyAction = document.querySelector('[data-checkin-history-action]');

  const setText = (node, message, tone) => {
    if (!node) return;
    node.dataset.tone = tone;
    node.textContent = message;
  };

  const requestKey = () => {
    if (global.crypto && typeof global.crypto.randomUUID === 'function') return 'checkin-' + global.crypto.randomUUID();
    const bytes = new Uint8Array(16);
    if (global.crypto && typeof global.crypto.getRandomValues === 'function') global.crypto.getRandomValues(bytes);
    else for (let i = 0; i < bytes.length; i += 1) bytes[i] = Math.floor(Math.random() * 256);
    return 'checkin-' + Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
  };

  const stopDecodeLoop = () => {
    state.decoding = false;
    if (state.frameId) {
      global.cancelAnimationFrame(state.frameId);
      state.frameId = 0;
    }
  };

  const cleanupStream = () => {
    state.cameraGeneration += 1;
    state.cameraPending = false;
    stopDecodeLoop();
    if (state.stream) {
      state.stream.getTracks().forEach(track => track.stop());
      state.stream = null;
    }
    if (video) { video.srcObject = null; video.hidden = true; }
    if (placeholder) placeholder.hidden = false;
    if (startButton) startButton.hidden = false;
    if (stopButton) stopButton.hidden = true;
  };

  const appendText = (parent, tag, className, value) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = String(value ?? '');
    parent.appendChild(node);
    return node;
  };

  const formatDate = (value) => {
    const parsed = value ? new Date(value) : new Date();
    return Number.isNaN(parsed.getTime()) ? 'Vừa check-in' : parsed.toLocaleString('vi-VN');
  };

  const renderRecord = (item) => {
    const record = document.createElement('article');
    record.className = 'learner-checkin-record';
    record.dataset.checkinRecord = 'server';
    const icon = appendText(record, 'span', 'learner-checkin-record__icon', '✓');
    icon.setAttribute('aria-hidden', 'true');
    const content = document.createElement('div');
    content.className = 'learner-checkin-record__content';
    appendText(content, 'h3', '', item && item.activity ? item.activity.title : 'Check-in');
    const meta = document.createElement('p');
    appendText(meta, 'span', '', formatDate(item && item.checkedInAt));
    content.appendChild(meta);
    record.appendChild(content);
    const status = document.createElement('div');
    status.className = 'learner-checkin-record__status';
    appendText(status, 'span', 'learner-verified-badge', 'Đã xác nhận');
    appendText(status, 'strong', '', '+' + (item && item.experience ? item.experience.hours : '0.00') + 'h');
    record.appendChild(status);
    return record;
  };

  const renderHistory = (items) => {
    if (!historyList) return;
    historyList.replaceChildren();
    const rows = Array.isArray(items) ? items : [];
    if (rows.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'learner-empty-state';
      empty.textContent = 'Chưa có lượt check-in được xác nhận.';
      historyList.appendChild(empty);
      return;
    }
    rows.forEach(item => historyList.appendChild(renderRecord(item)));
  };

  const loadHistory = async () => {
    if (!api) return;
    try {
      const payload = await api.get('/checkins.php?limit=25');
      renderHistory(payload && payload.items);
    } catch (error) {
      setText(apiState, error && error.message ? error.message : 'Không thể tải lịch sử check-in.', 'warn');
    }
  };

  const submitToken = async (token) => {
    if (!api || state.submitting) return;
    state.submitting = true;
    cleanupStream();
    if (submitButton) submitButton.disabled = true;
    try {
      const result = await api.send('POST', '/checkins.php', { token }, { idempotencyKey: requestKey() });
      if (feedback) feedback.textContent = '';
      if (global.Swal) {
        global.Swal.fire({
          icon: 'success',
          title: 'Check-in thành công!',
          text: 'Bạn đã nhận được +' + (result.experience?.hours || '0.00') + 'h từ ' + (result.activity?.title || 'hoạt động') + '.',
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 4000,
          timerProgressBar: true,
          background: '#ecfdf5',
          color: '#065f46'
        });
      }
      setText(apiState, 'Đã xác nhận ' + (result.experience?.hours || '0.00') + ' giờ', 'success');
      await loadHistory();
      if (historyAction) {
        historyAction.hidden = false;
        historyAction.style.removeProperty('display');
      }
      if (tokenField) tokenField.value = '';
    } catch (error) {
      const code = error && error.code ? error.code : '';
      let errorMsg = error && error.message ? error.message : 'Không thể hoàn tất check-in.';
      if (code === 'CHECKIN_ALREADY_EXISTS') errorMsg = 'Mã này đã được check-in trước đó.';
      else if (code === 'QR_SESSION_EXPIRED') errorMsg = 'Phiên QR đã hết hạn.';
      else if (code === 'QR_SESSION_REVOKED') errorMsg = 'Phiên QR đã bị thu hồi.';
      else if (code === 'QR_SESSION_EXHAUSTED') errorMsg = 'Phiên QR đã hết lượt quét.';
      else if (code === 'QR_TOKEN_INVALID') errorMsg = 'Token không hợp lệ.';
      
      if (feedback) feedback.textContent = '';
      if (global.Swal) {
        global.Swal.fire({
          icon: 'error',
          title: 'Lỗi Check-in',
          text: errorMsg,
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 4000,
          timerProgressBar: true,
          background: '#fef2f2',
          color: '#991b1b'
        });
      }
      setText(apiState, 'Check-in failed', 'error');
    } finally {
      state.submitting = false;
      if (submitButton) submitButton.disabled = false;
    }
  };

  const decodeFrame = async () => {
    if (!state.decoding || !state.detector || !video || video.readyState < 2) {
      if (state.decoding) state.frameId = global.requestAnimationFrame(decodeFrame);
      return;
    }
    const generation = state.cameraGeneration;
    try {
      const codes = await state.detector.detect(video);
      if (generation !== state.cameraGeneration || !state.decoding || document.hidden) return;
      const value = codes && codes[0] && (codes[0].rawValue || codes[0].rawData);
      if (value) {
        const token = String(value).trim();
        if (token) await submitToken(token);
        return;
      }
    } catch {
      if (generation !== state.cameraGeneration) return;
      cleanupStream();
      setText(apiState, 'Decoder unsupported', 'warn');
      return;
    }
    if (state.decoding) state.frameId = global.requestAnimationFrame(decodeFrame);
  };

  const startCamera = async () => {
    if (!global.navigator || !global.navigator.mediaDevices || typeof global.navigator.mediaDevices.getUserMedia !== 'function') {
      setText(feedback, 'Trình duyệt này không hỗ trợ camera. Hãy dùng token thủ công.', 'warn');
      setText(apiState, 'Unsupported camera API', 'warn');
      return;
    }
    const detector = createFrameDecoder({
      BarcodeDetector: global.BarcodeDetector,
      jsQR: global.jsQR,
      canvasFactory: () => document.createElement('canvas'),
    });
    if (!detector) {
      setText(feedback, 'Không hỗ trợ bộ giải mã QR trên trình duyệt này. Hãy dùng token thủ công.', 'warn');
      setText(apiState, 'unsupported-decoder', 'warn');
      return;
    }
    let generation = state.cameraGeneration;
    try {
      cleanupStream();
      generation = state.cameraGeneration;
      state.cameraPending = true;
      if (startButton) startButton.disabled = true;
      state.detector = detector;
      const stream = await global.navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
      if (generation !== state.cameraGeneration || document.hidden) {
        stream.getTracks().forEach(track => track.stop());
        return;
      }
      state.cameraPending = false;
      state.stream = stream;
      if (video) {
        video.srcObject = stream;
        video.hidden = false;
        await video.play().catch(() => undefined);
        if (generation !== state.cameraGeneration || document.hidden || state.stream !== stream) {
          stream.getTracks().forEach(track => track.stop());
          return;
        }
      }
      if (placeholder) placeholder.hidden = true;
      if (startButton) startButton.hidden = true;
      if (stopButton) stopButton.hidden = false;
      setText(feedback, 'Camera đã sẵn sàng. Đưa mã QR vào khung hình.', 'success');
      setText(apiState, 'Camera active', 'success');
      state.decoding = true;
      state.frameId = global.requestAnimationFrame(decodeFrame);
    } catch (error) {
      if (generation !== state.cameraGeneration) return;
      cleanupStream();
      if (error && (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError')) { setText(feedback, 'Quyền camera bị từ chối. Hãy dùng token thủ công.', 'warn'); setText(apiState, 'Permission denied', 'warn'); }
      else { setText(feedback, 'Không thể mở camera. Hãy dùng token thủ công.', 'warn'); setText(apiState, 'Camera unavailable', 'warn'); }
    } finally {
      if (startButton && !state.cameraPending) startButton.disabled = false;
    }
  };

  if (startButton) startButton.addEventListener('click', startCamera);
  if (stopButton) stopButton.addEventListener('click', cleanupStream);
  if (form) form.addEventListener('submit', (event) => {
    event.preventDefault();
    cleanupStream();
    const token = String(tokenField?.value || '').trim();
    if (!token) { setText(feedback, 'Vui lòng nhập token QR.', 'warn'); return; }
    submitToken(token);
  });
  if (resetButton) resetButton.addEventListener('click', () => { cleanupStream(); if (tokenField) tokenField.value = ''; setText(feedback, 'Đã xóa token thủ công.', 'info'); });
  global.addEventListener('beforeunload', cleanupStream);
  document.addEventListener('visibilitychange', () => { if (document.hidden) cleanupStream(); });
  loadHistory();

  const exported = { createFrameDecoder, renderHistory, cleanupStream, requestKey, startCamera, submitToken, loadHistory };
  if (typeof module !== 'undefined' && module.exports) module.exports = exported;
  global.LearnerCheckin = exported;
})(typeof window !== 'undefined' ? window : globalThis);
