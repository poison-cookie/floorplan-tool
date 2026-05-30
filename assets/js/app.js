(function () {
  const maxFiles = 50;
  const allowedExtensions = new Set(['jpg', 'jpeg', 'png', 'gif']);
  const clipboardMimeExtensions = {
    'image/jpeg': 'jpg',
    'image/png': 'png',
    'image/gif': 'gif'
  };
  const forbiddenFilenameChars = /[\\/:*?"<>|]+/g;

  const uploadForm = document.getElementById('upload-form');
  if (uploadForm) {
    const fileInput = document.getElementById('images');
    const fileList = document.getElementById('file-list');
    const fileCount = document.getElementById('file-count');
    const warning = document.getElementById('client-warning');
    const pasteZone = document.getElementById('paste-zone');
    const pasteStatus = document.getElementById('paste-status');
    const cropModal = document.getElementById('crop-modal');
    const cropCanvas = document.getElementById('crop-canvas');
    const cropApplyButton = document.getElementById('crop-apply');
    const cropCancelButton = document.getElementById('crop-cancel');
    const cropCloseButton = document.getElementById('crop-close');
    const cropResetButton = document.getElementById('crop-reset');
    const cropStatus = document.getElementById('crop-status');
    const cropContext = cropCanvas ? cropCanvas.getContext('2d') : null;
    let cropState = null;
    let pastedImageIndex = 1;
    let selectedFiles = Array.from(fileInput.files || []);
    let selectedFileNames = selectedFiles.map(function (file, index) {
      return defaultUploadFilename(file, index);
    });
    let previewObjectUrls = [];

    const updateSelectedFiles = function () {
      const files = selectedFiles;
      const invalidFiles = files.filter(function (file) {
        return !allowedExtensions.has(getExtension(file.name));
      });

      ensureSelectedFileNames();
      revokePreviewObjectUrls();
      fileList.replaceChildren();
      files.forEach(function (file, index) {
        const item = document.createElement('li');
        item.className = 'file-list-item';

        const preview = document.createElement('img');
        preview.className = 'file-preview-image';
        preview.alt = file.name + ' のプレビュー';
        preview.src = createPreviewObjectUrl(file);
        item.appendChild(preview);

        const details = document.createElement('div');
        details.className = 'file-details';

        const originalName = document.createElement('span');
        originalName.className = 'file-name';
        originalName.textContent = file.name;
        details.appendChild(originalName);

        const nameLabel = document.createElement('label');
        nameLabel.className = 'upload-filename-label';
        nameLabel.textContent = '加工後のファイル名';

        const nameInput = document.createElement('input');
        nameInput.className = 'upload-filename-input';
        nameInput.type = 'text';
        nameInput.name = 'upload_filenames[]';
        nameInput.value = selectedFileNames[index] || defaultUploadFilename(file, index);
        nameInput.dataset.filenameIndex = String(index);
        nameInput.autocomplete = 'off';

        nameLabel.appendChild(nameInput);
        details.appendChild(nameLabel);
        item.appendChild(details);

        const actions = document.createElement('div');
        actions.className = 'file-item-actions';

        if (canCropFile(file)) {
          const cropButton = document.createElement('button');
          cropButton.type = 'button';
          cropButton.className = 'file-action-button';
          cropButton.dataset.cropIndex = String(index);
          cropButton.textContent = '範囲削除';
          actions.appendChild(cropButton);
        }

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'file-action-button file-action-danger';
        removeButton.dataset.removeIndex = String(index);
        removeButton.textContent = '一覧から削除';
        actions.appendChild(removeButton);
        item.appendChild(actions);
        fileList.appendChild(item);
      });

      fileCount.textContent = files.length === 0
        ? 'ファイルはまだ選択されていません。'
        : files.length + '枚のファイルが選択されています。';

      const messages = [];
      if (files.length > maxFiles) {
        messages.push('一度に処理できる画像は' + maxFiles + '枚までです。');
      }

      if (invalidFiles.length > 0) {
        messages.push('対応していない拡張子のファイルが含まれています。');
      }

      warning.hidden = messages.length === 0;
      warning.textContent = messages.join(' ');
    };

    const setFiles = function (files) {
      const nextFiles = Array.from(files || []);
      const previousNames = selectedFileNames.slice();
      selectedFiles = nextFiles;
      selectedFileNames = previousNames.slice(0, nextFiles.length);
      ensureSelectedFileNames();

      applyFilesToInput();
      updateSelectedFiles();
    };

    const setFilesAndNames = function (files, names) {
      selectedFiles = Array.from(files || []);
      selectedFileNames = Array.isArray(names) ? names.slice(0, selectedFiles.length) : [];
      ensureSelectedFileNames();
      applyFilesToInput();
      updateSelectedFiles();
    };

    const applyFilesToInput = function () {
      if (typeof DataTransfer === 'undefined') {
        return;
      }

      const transfer = new DataTransfer();
      selectedFiles.forEach(function (file) {
        transfer.items.add(file);
      });
      fileInput.files = transfer.files;
    };

    const appendFiles = function (files) {
      const appendedFiles = Array.from(files || []);
      setFilesAndNames(
        selectedFiles.concat(appendedFiles),
        selectedFileNames.concat(appendedFiles.map(function (file, index) {
          return defaultUploadFilename(file, selectedFiles.length + index);
        }))
      );
    };

    const handleFileInputChange = function () {
      const addedFiles = Array.from(fileInput.files || []);
      if (addedFiles.length === 0) {
        setFiles(selectedFiles);
        return;
      }

      if (selectedFiles.length + addedFiles.length > maxFiles) {
        warning.hidden = false;
        warning.textContent = '\u4e00\u5ea6\u306b\u51e6\u7406\u3067\u304d\u308b\u753b\u50cf\u306f' + maxFiles + '\u679a\u307e\u3067\u3067\u3059\u3002';
        setFiles(selectedFiles);
        return;
      }

      setFiles(selectedFiles.concat(addedFiles));
    };

    const removeSelectedFile = function (index) {
      if (!Number.isInteger(index) || index < 0 || index >= selectedFiles.length) {
        return;
      }

      const files = selectedFiles.slice();
      const names = selectedFileNames.slice();
      files.splice(index, 1);
      names.splice(index, 1);
      setFilesAndNames(files, names);
      showPasteStatus('\u9078\u629e\u304b\u3089\u524a\u9664\u3057\u307e\u3057\u305f\u3002');
    };

    const showPasteStatus = function (message) {
      if (pasteStatus) {
        pasteStatus.textContent = message;
      }
    };

    const extractImageFilesFromPaste = function (event) {
      const data = event.clipboardData;
      if (!data || !data.items) {
        return [];
      }

      return Array.from(data.items).reduce(function (files, item) {
        if (item.kind !== 'file' || !item.type.startsWith('image/')) {
          return files;
        }

        const extension = clipboardMimeExtensions[item.type];
        const blob = item.getAsFile();
        if (!blob || !extension) {
          return files;
        }

        const filename = nextScreenshotFilename(extension);
        files.push(new File([blob], filename, {
          type: item.type,
          lastModified: Date.now()
        }));
        return files;
      }, []);
    };

    const handlePaste = function (event) {
      if (typeof DataTransfer === 'undefined') {
        showPasteStatus('このブラウザでは貼り付け画像をファイルとして追加できません。');
        return;
      }

      const pastedFiles = extractImageFilesFromPaste(event);
      if (pastedFiles.length === 0) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      const currentCount = selectedFiles.length;
      if (currentCount + pastedFiles.length > maxFiles) {
        showPasteStatus('一度に処理できる画像は' + maxFiles + '枚までです。');
        warning.hidden = false;
        warning.textContent = '一度に処理できる画像は' + maxFiles + '枚までです。';
        return;
      }

      appendFiles(pastedFiles);
      showPasteStatus(pastedFiles.length + '枚の貼り付け画像を追加しました。');
      if (pasteZone) {
        pasteZone.classList.add('paste-zone-active');
        window.setTimeout(function () {
          pasteZone.classList.remove('paste-zone-active');
        }, 700);
      }
    };

    const nextScreenshotFilename = function (extension) {
      const filename = 'screenshot_' + String(pastedImageIndex).padStart(3, '0') + '.' + extension;
      pastedImageIndex++;
      return filename;
    };

    fileInput.addEventListener('change', handleFileInputChange);
    fileList.addEventListener('input', function (event) {
      const input = event.target.closest('[data-filename-index]');
      if (!input) {
        return;
      }

      const index = Number(input.dataset.filenameIndex);
      if (!Number.isInteger(index) || index < 0 || index >= selectedFiles.length) {
        return;
      }

      const sanitized = sanitizeFilename(input.value);
      if (input.value !== sanitized) {
        input.value = sanitized;
      }
      selectedFileNames[index] = sanitized;
    });

    fileList.addEventListener('click', function (event) {
      const removeButton = event.target.closest('[data-remove-index]');
      if (removeButton) {
        removeSelectedFile(Number(removeButton.dataset.removeIndex));
        return;
      }

      const cropButton = event.target.closest('[data-crop-index]');
      if (!cropButton) {
        return;
      }

      const index = Number(cropButton.dataset.cropIndex);
      openCropModal(index);
    });

    document.addEventListener('paste', handlePaste);
    if (pasteZone) {
      pasteZone.addEventListener('paste', handlePaste);
      pasteZone.addEventListener('click', function () {
        pasteZone.focus();
        showPasteStatus('この枠にフォーカスした状態で貼り付けできます。');
      });
    }

    uploadForm.addEventListener('submit', function (event) {
      const files = selectedFiles;
      const hasInvalidFile = files.some(function (file) {
        return !allowedExtensions.has(getExtension(file.name));
      });

      if (files.length === 0 || files.length > maxFiles || hasInvalidFile) {
        event.preventDefault();
        updateSelectedFiles();

        if (files.length === 0) {
          warning.hidden = false;
          warning.textContent = '画像ファイルを選択してください。';
        }
        return;
      }

      syncSelectedFileNamesFromInputs();
      setFilesAndNames(files, selectedFileNames);
    });

    if (cropCanvas) {
      cropCanvas.addEventListener('pointerdown', startCropSelection);
      cropCanvas.addEventListener('pointermove', moveCropSelection);
      cropCanvas.addEventListener('pointerup', finishCropSelection);
      cropCanvas.addEventListener('pointerleave', finishCropSelection);
    }

    if (cropApplyButton) {
      cropApplyButton.addEventListener('click', applyCrop);
    }

    [cropCancelButton, cropCloseButton].forEach(function (button) {
      if (button) {
        button.addEventListener('click', closeCropModal);
      }
    });

    if (cropResetButton) {
      cropResetButton.addEventListener('click', resetCropSelection);
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && cropModal && !cropModal.hidden) {
        closeCropModal();
      }
    });

    function canCropFile(file) {
      return file && file.type && Object.prototype.hasOwnProperty.call(clipboardMimeExtensions, file.type);
    }

    function openCropModal(index) {
      if (!cropModal || !cropCanvas || !cropContext || !Number.isInteger(index)) {
        return;
      }

      const files = selectedFiles;
      const file = files[index];
      if (!canCropFile(file)) {
        showPasteStatus('この画像形式は画面上で切り取りできません。');
        return;
      }

      closeCropModal();

      const image = new Image();
      const objectUrl = URL.createObjectURL(file);

      image.onload = function () {
        const maxWidth = Math.min(window.innerWidth - 72, 920);
        const maxHeight = Math.min(window.innerHeight - 240, 640);
        const scale = Math.min(maxWidth / image.naturalWidth, maxHeight / image.naturalHeight, 1);
        const canvasWidth = Math.max(1, Math.round(image.naturalWidth * scale));
        const canvasHeight = Math.max(1, Math.round(image.naturalHeight * scale));

        cropCanvas.width = canvasWidth;
        cropCanvas.height = canvasHeight;
        cropState = {
          fileIndex: index,
          file: file,
          image: image,
          objectUrl: objectUrl,
          scale: scale,
          dragging: false,
          startX: 0,
          startY: 0,
          selection: {
            x: 0,
            y: 0,
            width: 0,
            height: 0
          }
        };

        cropModal.hidden = false;
        document.body.classList.add('modal-open');
        setCropStatus(file.name + ' - 削除する範囲をドラッグしてください。');
        drawCropCanvas();
        cropApplyButton.focus();
      };

      image.onerror = function () {
        URL.revokeObjectURL(objectUrl);
        showPasteStatus('画像を読み込めませんでした。');
      };

      image.src = objectUrl;
    }

    function closeCropModal() {
      if (cropState && cropState.objectUrl) {
        URL.revokeObjectURL(cropState.objectUrl);
      }

      cropState = null;
      if (cropModal) {
        cropModal.hidden = true;
      }
      document.body.classList.remove('modal-open');
      setCropStatus('');
    }

    function startCropSelection(event) {
      if (!cropState) {
        return;
      }

      const point = getCanvasPoint(event);
      cropState.dragging = true;
      cropState.startX = point.x;
      cropState.startY = point.y;
      cropState.selection = {
        x: point.x,
        y: point.y,
        width: 0,
        height: 0
      };
      cropCanvas.setPointerCapture(event.pointerId);
      drawCropCanvas();
    }

    function moveCropSelection(event) {
      if (!cropState || !cropState.dragging) {
        return;
      }

      const point = getCanvasPoint(event);
      cropState.selection = normalizeSelection({
        x: cropState.startX,
        y: cropState.startY,
        width: point.x - cropState.startX,
        height: point.y - cropState.startY
      });
      drawCropCanvas();
    }

    function finishCropSelection(event) {
      if (!cropState || !cropState.dragging) {
        return;
      }

      cropState.dragging = false;
      if (cropCanvas.hasPointerCapture(event.pointerId)) {
        cropCanvas.releasePointerCapture(event.pointerId);
      }

      const selection = normalizeSelection(cropState.selection);
      if (selection.width < 4 || selection.height < 4) {
        resetCropSelection();
        return;
      }

      cropState.selection = selection;
      drawCropCanvas();
    }

    function resetCropSelection() {
      if (!cropState) {
        return;
      }

      cropState.selection = {
        x: 0,
        y: 0,
        width: 0,
        height: 0
      };
      setCropStatus(cropState.file.name + ' - 削除する範囲をドラッグしてください。');
      drawCropCanvas();
    }

    function applyCrop() {
      if (!cropState) {
        return;
      }

      const selection = normalizeSelection(cropState.selection);
      if (selection.width < 4 || selection.height < 4) {
        setCropStatus('削除する範囲をドラッグで選択してください。');
        return;
      }

      const sourceX = Math.max(0, Math.round(selection.x / cropState.scale));
      const sourceY = Math.max(0, Math.round(selection.y / cropState.scale));
      const sourceWidth = Math.min(
        cropState.image.naturalWidth - sourceX,
        Math.max(1, Math.round(selection.width / cropState.scale))
      );
      const sourceHeight = Math.min(
        cropState.image.naturalHeight - sourceY,
        Math.max(1, Math.round(selection.height / cropState.scale))
      );
      const outputCanvas = document.createElement('canvas');
      outputCanvas.width = cropState.image.naturalWidth;
      outputCanvas.height = cropState.image.naturalHeight;
      const outputContext = outputCanvas.getContext('2d');

      outputContext.drawImage(cropState.image, 0, 0);
      outputContext.fillStyle = '#ffffff';
      outputContext.fillRect(sourceX, sourceY, sourceWidth, sourceHeight);

      outputCanvas.toBlob(function (blob) {
        if (!blob) {
          setCropStatus('削除後の画像を作成できませんでした。');
          return;
        }

        const files = selectedFiles.slice();
        const names = selectedFileNames.slice();
        const filename = buildErasedFilename(cropState.file.name);
        const nextFile = new File([blob], filename, {
          type: 'image/png',
          lastModified: Date.now()
        });
        if (names[cropState.fileIndex] === defaultUploadFilename(cropState.file, cropState.fileIndex)) {
          names[cropState.fileIndex] = defaultUploadFilename(nextFile, cropState.fileIndex);
        }
        files[cropState.fileIndex] = nextFile;
        setFilesAndNames(files, names);
        showPasteStatus(filename + ' に選択範囲の削除を適用しました。');
        closeCropModal();
      }, 'image/png');
    }

    function drawCropCanvas() {
      if (!cropState) {
        return;
      }

      cropContext.clearRect(0, 0, cropCanvas.width, cropCanvas.height);
      cropContext.drawImage(cropState.image, 0, 0, cropCanvas.width, cropCanvas.height);

      const selection = normalizeSelection(cropState.selection);
      if (selection.width < 1 || selection.height < 1) {
        return;
      }

      cropContext.save();
      cropContext.fillStyle = 'rgba(0, 0, 0, 0.42)';
      cropContext.fillRect(0, 0, cropCanvas.width, cropCanvas.height);
      cropContext.clearRect(selection.x, selection.y, selection.width, selection.height);
      cropContext.drawImage(
        cropState.image,
        selection.x / cropState.scale,
        selection.y / cropState.scale,
        selection.width / cropState.scale,
        selection.height / cropState.scale,
        selection.x,
        selection.y,
        selection.width,
        selection.height
      );
      cropContext.strokeStyle = '#ffffff';
      cropContext.lineWidth = 2;
      cropContext.strokeRect(selection.x + 1, selection.y + 1, selection.width - 2, selection.height - 2);
      cropContext.strokeStyle = '#126f83';
      cropContext.lineWidth = 2;
      cropContext.setLineDash([8, 5]);
      cropContext.strokeRect(selection.x + 5, selection.y + 5, Math.max(0, selection.width - 10), Math.max(0, selection.height - 10));
      cropContext.restore();
    }

    function getCanvasPoint(event) {
      const rect = cropCanvas.getBoundingClientRect();
      return {
        x: clamp((event.clientX - rect.left) * (cropCanvas.width / rect.width), 0, cropCanvas.width),
        y: clamp((event.clientY - rect.top) * (cropCanvas.height / rect.height), 0, cropCanvas.height)
      };
    }

    function normalizeSelection(selection) {
      const x = selection.width < 0 ? selection.x + selection.width : selection.x;
      const y = selection.height < 0 ? selection.y + selection.height : selection.y;
      const width = Math.abs(selection.width);
      const height = Math.abs(selection.height);

      return {
        x: clamp(x, 0, cropCanvas.width),
        y: clamp(y, 0, cropCanvas.height),
        width: clamp(width, 0, cropCanvas.width - clamp(x, 0, cropCanvas.width)),
        height: clamp(height, 0, cropCanvas.height - clamp(y, 0, cropCanvas.height))
      };
    }

    function clamp(value, min, max) {
      return Math.min(Math.max(value, min), max);
    }

    function buildErasedFilename(filename) {
      const base = sanitizeFilename(filename) || 'erased';
      return base.replace(/_erased$/u, '') + '_erased.png';
    }

    function defaultUploadFilename(file, index) {
      return sanitizeFilename(file && file.name ? file.name : '') || 'floorplan_' + String(index + 1).padStart(3, '0');
    }

    function ensureSelectedFileNames() {
      selectedFileNames = selectedFiles.map(function (file, index) {
        return selectedFileNames[index] !== undefined
          ? selectedFileNames[index]
          : defaultUploadFilename(file, index);
      });
    }

    function syncSelectedFileNamesFromInputs() {
      fileList.querySelectorAll('[data-filename-index]').forEach(function (input) {
        const index = Number(input.dataset.filenameIndex);
        if (Number.isInteger(index) && index >= 0 && index < selectedFiles.length) {
          selectedFileNames[index] = sanitizeFilename(input.value);
        }
      });
      ensureSelectedFileNames();
    }

    function createPreviewObjectUrl(file) {
      const objectUrl = URL.createObjectURL(file);
      previewObjectUrls.push(objectUrl);
      return objectUrl;
    }

    function revokePreviewObjectUrls() {
      previewObjectUrls.forEach(function (objectUrl) {
        URL.revokeObjectURL(objectUrl);
      });
      previewObjectUrls = [];
    }

    function setCropStatus(message) {
      if (cropStatus) {
        cropStatus.textContent = message;
      }
    }
  }

  document.querySelectorAll('.filename-input').forEach(function (input) {
    input.addEventListener('input', function () {
      const sanitized = sanitizeFilename(input.value);
      if (input.value !== sanitized) {
        input.value = sanitized;
      }
    });
  });

  document.querySelectorAll('.download-form').forEach(function (form) {
    form.addEventListener('submit', function () {
      const input = form.querySelector('.filename-input');
      if (!input) {
        return;
      }

      input.value = sanitizeFilename(input.value) || defaultFilenameForCard(form.closest('.result-card'));
    });
  });

  const zipForm = document.getElementById('zip-form');
  if (zipForm) {
    zipForm.addEventListener('submit', function () {
      const holder = document.getElementById('zip-filename-fields');
      const folderInput = zipForm.querySelector('input[name="folder_name"]');
      const cards = Array.from(document.querySelectorAll('.result-card[data-image-id]'))
        .filter(function (card) {
          return Boolean(card.querySelector('.filename-input'));
        });
      const used = new Set();
      let blankIndex = 1;

      if (folderInput) {
        folderInput.value = sanitizeFilename(folderInput.value);
      }

      holder.replaceChildren();
      cards.forEach(function (card) {
        const imageId = card.getAttribute('data-image-id');
        const input = card.querySelector('.filename-input');

        if (!imageId || !input) {
          return;
        }

        let base = sanitizeFilename(input.value);
        if (!base) {
          base = 'floorplan_' + String(blankIndex).padStart(3, '0');
          blankIndex++;
        }

        base = makeUniqueBase(base, used);
        input.value = base;

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'filenames[' + imageId + ']';
        hidden.value = base;
        holder.appendChild(hidden);
      });
    });
  }

  const resultGrid = document.querySelector('.result-grid[data-batch-id]');
  if (resultGrid) {
    resultGrid.addEventListener('click', function (event) {
      const deleteButton = event.target.closest('[data-delete-image]');
      if (deleteButton) {
        const card = deleteButton.closest('.result-card[data-image-id]');
        if (card) {
          deleteResultItem(resultGrid, card);
        }
        return;
      }

      const button = event.target.closest('[data-position-action]');
      if (!button) {
        return;
      }

      const card = button.closest('.result-card[data-image-id]');
      if (!card) {
        return;
      }

      adjustPosition(resultGrid, card, button.dataset.positionAction);
    });
  }

  function getExtension(filename) {
    const index = filename.lastIndexOf('.');
    return index === -1 ? '' : filename.slice(index + 1).toLowerCase();
  }

  function sanitizeFilename(filename) {
    return filename
      .trim()
      .replace(/\.[A-Za-z0-9]{1,8}$/u, '')
      .replace(forbiddenFilenameChars, '_')
      .replace(/[\x00-\x1F\x7F]/g, '')
      .replace(/^\.+|\.+$/g, '')
      .trim();
  }

  function defaultFilenameForCard(card) {
    const index = card ? Number(card.getAttribute('data-card-index')) : 1;
    const safeIndex = Number.isFinite(index) && index > 0 ? index : 1;
    return 'floorplan_' + String(safeIndex).padStart(3, '0');
  }

  function makeUniqueBase(base, used) {
    let candidate = base;
    let suffix = 2;

    while (used.has(candidate.toLocaleLowerCase())) {
      candidate = base + '_' + suffix;
      suffix++;
    }

    used.add(candidate.toLocaleLowerCase());
    return candidate;
  }

  async function deleteResultItem(resultGrid, card) {
    if (!window.confirm('この画像を削除しますか？')) {
      return;
    }

    const buttons = Array.from(card.querySelectorAll('button'));
    const formData = new FormData();

    formData.append('csrf_token', resultGrid.dataset.csrfToken || '');
    formData.append('batch_id', resultGrid.dataset.batchId || '');
    formData.append('image_id', card.dataset.imageId || '');

    buttons.forEach(function (button) {
      button.disabled = true;
    });

    try {
      const response = await fetch('delete_image.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });
      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || '画像を削除できませんでした。');
      }

      card.remove();
      updateResultCounts(
        Number(data.success_count || 0),
        Number(data.total_count || 0),
        Number(data.error_count || 0)
      );
      refreshResultCards();
    } catch (error) {
      window.alert(error.message || '画像を削除できませんでした。');
      buttons.forEach(function (button) {
        button.disabled = false;
      });
    }
  }

  async function adjustPosition(resultGrid, card, action) {
    const buttons = Array.from(card.querySelectorAll('[data-position-action]'));
    const status = card.querySelector('.position-status');
    const formData = new FormData();

    formData.append('csrf_token', resultGrid.dataset.csrfToken || '');
    formData.append('batch_id', resultGrid.dataset.batchId || '');
    formData.append('image_id', card.dataset.imageId || '');
    formData.append('action', action || '');

    buttons.forEach(function (button) {
      button.disabled = true;
    });
    setPositionStatusText(status, '更新中...');

    try {
      const response = await fetch('adjust_position.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });
      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || '位置を更新できませんでした。');
      }

      const image = card.querySelector('.preview-image');
      const link = card.querySelector('.preview-link');
      if (image && data.image_url) {
        image.src = data.image_url;
      }
      if (link && data.image_url) {
        link.href = data.image_url;
      }

      card.dataset.offsetX = String(data.offset_x || 0);
      card.dataset.offsetY = String(data.offset_y || 0);
      renderPositionStatus(status, Number(data.offset_x || 0), Number(data.offset_y || 0));
    } catch (error) {
      setPositionStatusText(status, error.message || '位置を更新できませんでした。');
    } finally {
      buttons.forEach(function (button) {
        button.disabled = false;
      });
    }
  }

  function updateResultCounts(successCount, totalCount, errorCount) {
    const successCounter = document.querySelector('[data-success-count]');
    const totalCounter = document.querySelector('[data-total-count]');
    const errorCounter = document.querySelector('[data-error-count]');
    const errorWarning = document.querySelector('[data-error-warning]');
    const emptyResults = document.querySelector('[data-empty-results]');
    const zipFormElement = document.getElementById('zip-form');

    if (successCounter) {
      successCounter.textContent = String(successCount);
    }
    if (totalCounter) {
      totalCounter.textContent = String(totalCount);
    }
    if (errorCounter) {
      errorCounter.textContent = String(errorCount);
    }
    if (errorWarning) {
      errorWarning.hidden = errorCount === 0;
    }
    if (emptyResults) {
      emptyResults.hidden = totalCount > 0;
    }
    if (zipFormElement) {
      zipFormElement.hidden = successCount === 0;
    }
  }

  function refreshResultCards() {
    Array.from(document.querySelectorAll('.result-card[data-image-id]')).forEach(function (card, index) {
      const cardNumber = index + 1;
      const itemNumber = card.querySelector('.item-number');

      card.dataset.cardIndex = String(cardNumber);
      if (itemNumber) {
        itemNumber.textContent = 'No.' + cardNumber;
      }
    });
  }

  function renderPositionStatus(status, offsetX, offsetY) {
    if (!status) {
      return;
    }

    status.replaceChildren(
      document.createTextNode('横: '),
      createOffsetSpan('offset-x', String(offsetX)),
      document.createTextNode('px / 縦: '),
      createOffsetSpan('offset-y', String(offsetY)),
      document.createTextNode('px')
    );
  }

  function createOffsetSpan(name, value) {
    const span = document.createElement('span');
    span.setAttribute('data-' + name, '');
    span.textContent = value;
    return span;
  }

  function setPositionStatusText(status, message) {
    if (status) {
      status.textContent = message;
    }
  }
})();
