/**
 * 네이버 블로그 스타일 한국어 에디터
 * Korean WYSIWYG Editor with Naver Blog style
 */

class KoreanEditor {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            height: '400px',
            placeholder: '내용을 입력하세요...',
            imageUploadUrl: '/admin/api/image_upload.php',
            fontSizes: ['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px'],
            fontFamilies: [
                { name: '맑은 고딕', value: 'font-malgun', css: 'Malgun Gothic, 맑은 고딕, sans-serif' },
                { name: '돋움', value: 'font-dotum', css: 'Dotum, 돋움, sans-serif' },
                { name: '굴림', value: 'font-gulim', css: 'Gulim, 굴림, sans-serif' },
                { name: '바탕', value: 'font-batang', css: 'Batang, 바탕, serif' },
                { name: '궁서', value: 'font-gungsuh', css: 'Gungsuh, 궁서, serif' },
                { name: 'Arial', value: 'font-arial', css: 'Arial, sans-serif' },
                { name: 'Times New Roman', value: 'font-times', css: 'Times New Roman, serif' },
                { name: 'Courier New', value: 'font-courier', css: 'Courier New, monospace' },
                { name: 'Georgia', value: 'font-georgia', css: 'Georgia, serif' },
                { name: 'Verdana', value: 'font-verdana', css: 'Verdana, sans-serif' }
            ],
            colors: [
                '#000000', '#333333', '#666666', '#999999', '#CCCCCC', '#FFFFFF', '#FF0000', '#FF9900',
                '#FFFF00', '#00FF00', '#00FFFF', '#0099FF', '#0066CC', '#9933FF', '#FF00FF', '#FF6699',
                '#FFE4E1', '#FFEFD5', '#F0FFF0', '#E1FFFF', '#E6F2FF', '#F0F8FF', '#F5F5DC', '#FDF5E6'
            ],
            emojis: ['😀', '😃', '😄', '😁', '😊', '😍', '🥰', '😘', '😗', '☺️', '😚', '😙', '🤗', '🤩', '🤔', '🤨', '😐', '😑', '😶', '🙄', '😏', '😣', '😥', '😮', '🤐', '😯', '😪', '😫', '🥱', '😴', '😌', '😛', '😜', '😝', '🤤', '😒', '😓', '😔', '😕', '🙃', '🤑', '😲', '☹️', '🙁', '😖', '😞', '😟', '😤', '😢', '😭', '😦', '😧', '😨', '😩', '🤯', '😬', '😰', '😱', '🥵', '🥶', '😳', '🤪', '😵', '🥴', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '😇', '🥳', '🥺', '🤠', '🤡', '🤫', '🤭', '🧐', '🤓', '😈', '👿', '👹', '👺', '💀', '👻', '👽', '🤖', '💩', '😺', '😸', '😹', '😻', '😼', '😽', '🙀', '😿', '😾'],
            ...options
        };
        
        this.isInitialized = false;
        this.currentRange = null;
        this.selectedImage = null;
        this.resizing = false;
        this.dragging = false;
        this.dragData = null;
        
        this.init();
    }
    
    init() {
        this.createEditor();
        this.bindEvents();
        this.isInitialized = true;
    }
    
    createEditor() {
        // 기존 textarea 숨기기
        const textarea = this.container.querySelector('textarea');
        if (textarea) {
            textarea.style.display = 'none';
            this.originalTextarea = textarea;
        }
        
        // 에디터 컨테이너 생성
        this.editorContainer = document.createElement('div');
        this.editorContainer.className = 'korean-editor-container';
        this.editorContainer.style.height = this.options.height;
        
        // 툴바 생성
        this.createToolbar();
        
        // 편집 영역 생성
        this.createEditArea();
        
        // 상태바 생성
        this.createStatusBar();
        
        // 다이얼로그들 생성
        this.createDialogs();
        
        // 컨테이너에 추가
        this.container.appendChild(this.editorContainer);
        
        // 폼 제출시 원본 textarea에 내용 복사
        this.setupFormSubmit();
    }
    
    createToolbar() {
        this.toolbar = document.createElement('div');
        this.toolbar.className = 'korean-editor-toolbar';
        
        const toolbarHTML = `
            <!-- 텍스트 서식 그룹 -->
            <div class="toolbar-group">
                <select class="toolbar-select" data-command="formatBlock">
                    <option value="">일반</option>
                    <option value="h1">제목 1</option>
                    <option value="h2">제목 2</option>
                    <option value="h3">제목 3</option>
                    <option value="h4">제목 4</option>
                    <option value="h5">제목 5</option>
                    <option value="h6">제목 6</option>
                    <option value="blockquote">인용구</option>
                </select>
                <select class="toolbar-select font-family-select" data-command="fontName">
                    <option value="">글꼴</option>
                    ${this.options.fontFamilies.map(font => `<option value="${font.css}" class="${font.value}">${font.name}</option>`).join('')}
                </select>
                <select class="toolbar-select" data-command="fontSize">
                    <option value="">크기</option>
                    ${this.options.fontSizes.map(size => `<option value="${size}">${size}</option>`).join('')}
                </select>
            </div>
            
            <!-- 기본 서식 그룹 -->
            <div class="toolbar-group">
                <button class="toolbar-button" data-command="bold" title="굵게 (Ctrl+B)"><b>B</b></button>
                <button class="toolbar-button" data-command="italic" title="기울임 (Ctrl+I)"><i>I</i></button>
                <button class="toolbar-button" data-command="underline" title="밑줄 (Ctrl+U)"><u>U</u></button>
                <button class="toolbar-button" data-command="strikeThrough" title="취소선"><s>S</s></button>
            </div>
            
            <!-- 색상 그룹 -->
            <div class="toolbar-group">
                <div class="toolbar-dropdown">
                    <button class="toolbar-button" data-command="foreColor" title="글자색">
                        <span style="color: #000;">A</span>
                    </button>
                    <div class="color-palette" id="textColorPalette">
                        <div class="color-grid">
                            ${this.options.colors.map(color => `<div class="color-item" style="background: ${color}" data-color="${color}"></div>`).join('')}
                        </div>
                        <div class="color-input-group">
                            <input type="color" placeholder="직접 입력" />
                        </div>
                    </div>
                </div>
                <div class="toolbar-dropdown">
                    <button class="toolbar-button" data-command="backColor" title="배경색">
                        <span style="background: #FFFF00; padding: 2px;">A</span>
                    </button>
                    <div class="color-palette" id="bgColorPalette">
                        <div class="color-grid">
                            ${this.options.colors.map(color => `<div class="color-item" style="background: ${color}" data-color="${color}"></div>`).join('')}
                        </div>
                        <div class="color-input-group">
                            <input type="color" placeholder="직접 입력" />
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 정렬 그룹 -->
            <div class="toolbar-group">
                <button class="toolbar-button" data-command="justifyLeft" title="왼쪽 정렬">⇤</button>
                <button class="toolbar-button" data-command="justifyCenter" title="가운데 정렬">⇥</button>
                <button class="toolbar-button" data-command="justifyRight" title="오른쪽 정렬">⇥</button>
                <button class="toolbar-button" data-command="justifyFull" title="양쪽 정렬">⇤⇥</button>
            </div>
            
            <!-- 리스트 그룹 -->
            <div class="toolbar-group">
                <button class="toolbar-button" data-command="insertUnorderedList" title="글머리 기호">• 목록</button>
                <button class="toolbar-button" data-command="insertOrderedList" title="번호 매기기">1. 목록</button>
                <button class="toolbar-button" data-command="outdent" title="내어쓰기">⇤</button>
                <button class="toolbar-button" data-command="indent" title="들여쓰기">⇥</button>
            </div>
            
            <!-- 삽입 그룹 -->
            <div class="toolbar-group">
                <button class="toolbar-button" data-command="createLink" title="링크 삽입">🔗</button>
                <button class="toolbar-button" data-command="insertImage" title="이미지 삽입">🖼️</button>
                <button class="toolbar-button" data-command="insertVideo" title="동영상 삽입">🎥</button>
                <button class="toolbar-button" data-command="insertAudio" title="오디오 삽입">🎵</button>
                <button class="toolbar-button" data-command="insertTable" title="표 삽입">📊</button>
                <div class="toolbar-dropdown">
                    <button class="toolbar-button" data-command="insertEmoji" title="이모지">😀</button>
                    <div class="emoji-palette" id="emojiPalette">
                        <div class="emoji-grid">
                            ${this.options.emojis.map(emoji => `<div class="emoji-item" data-emoji="${emoji}">${emoji}</div>`).join('')}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 기타 그룹 -->
            <div class="toolbar-group">
                <button class="toolbar-button" data-command="removeFormat" title="서식 제거">🗑️</button>
                <button class="toolbar-button" data-command="undo" title="실행 취소 (Ctrl+Z)">↶</button>
                <button class="toolbar-button" data-command="redo" title="다시 실행 (Ctrl+Y)">↷</button>
            </div>
        `;
        
        this.toolbar.innerHTML = toolbarHTML;
        this.editorContainer.appendChild(this.toolbar);
    }
    
    createEditArea() {
        this.editArea = document.createElement('div');
        this.editArea.className = 'korean-editor-content';
        this.editArea.contentEditable = true;
        this.editArea.setAttribute('data-placeholder', this.options.placeholder);
        
        // 초기 내용 설정
        if (this.originalTextarea && this.originalTextarea.value) {
            this.editArea.innerHTML = this.originalTextarea.value;
        }
        
        this.editorContainer.appendChild(this.editArea);
    }
    
    createStatusBar() {
        this.statusBar = document.createElement('div');
        this.statusBar.className = 'editor-status-bar';
        this.statusBar.innerHTML = `
            <div class="editor-info">네이버 블로그 스타일 에디터</div>
            <div class="char-count">0자</div>
        `;
        this.editorContainer.appendChild(this.statusBar);
    }
    
    createDialogs() {
        // 링크 다이얼로그
        this.linkDialog = document.createElement('div');
        this.linkDialog.className = 'link-dialog';
        this.linkDialog.innerHTML = `
            <h3>링크 삽입</h3>
            <div class="link-dialog-group">
                <label>링크 텍스트</label>
                <input type="text" id="linkText" placeholder="표시될 텍스트">
            </div>
            <div class="link-dialog-group">
                <label>링크 URL</label>
                <input type="url" id="linkUrl" placeholder="https://example.com">
            </div>
            <div class="link-dialog-actions">
                <button class="link-dialog-btn" onclick="koreanEditor.cancelLink()">취소</button>
                <button class="link-dialog-btn primary" onclick="koreanEditor.insertLink()">삽입</button>
            </div>
        `;
        
        // 이미지 크기 조절 다이얼로그
        this.imageSizeDialog = document.createElement('div');
        this.imageSizeDialog.className = 'image-size-dialog';
        this.imageSizeDialog.innerHTML = `
            <h3>이미지 크기 조절</h3>
            <div class="size-input-group">
                <input type="number" id="imageWidth" placeholder="너비" min="1">
                <span>×</span>
                <input type="number" id="imageHeight" placeholder="높이" min="1">
                <label><input type="checkbox" id="maintainRatio" checked> 비율 유지</label>
            </div>
            <div class="size-presets">
                <button class="size-preset-btn" data-width="100" data-height="auto">소형</button>
                <button class="size-preset-btn" data-width="300" data-height="auto">중형</button>
                <button class="size-preset-btn" data-width="500" data-height="auto">대형</button>
                <button class="size-preset-btn" data-width="100%" data-height="auto">전체</button>
            </div>
            <div class="link-dialog-actions">
                <button class="link-dialog-btn" onclick="koreanEditor.cancelImageSize()">취소</button>
                <button class="link-dialog-btn primary" onclick="koreanEditor.applyImageSize()">적용</button>
            </div>
        `;
        
        // 이미지 컨텍스트 메뉴
        this.imageContextMenu = document.createElement('div');
        this.imageContextMenu.className = 'image-context-menu';
        this.imageContextMenu.innerHTML = `
            <div class="context-menu-item" data-action="resize">🔧 크기 조절</div>
            <div class="context-menu-item" data-action="align-left">⬅️ 왼쪽 정렬</div>
            <div class="context-menu-item" data-action="align-center">↔️ 가운데 정렬</div>
            <div class="context-menu-item" data-action="align-right">➡️ 오른쪽 정렬</div>
            <div class="context-menu-item" data-action="alt-text">📝 대체 텍스트</div>
            <div class="context-menu-item danger" data-action="delete">🗑️ 삭제</div>
        `;
        
        this.linkOverlay = document.createElement('div');
        this.linkOverlay.className = 'link-dialog-overlay';
        this.linkOverlay.onclick = () => this.hideAllDialogs();
        
        document.body.appendChild(this.linkOverlay);
        document.body.appendChild(this.linkDialog);
        document.body.appendChild(this.imageSizeDialog);
        document.body.appendChild(this.imageContextMenu);
        
        // 전역 참조 설정 (다이얼로그 버튼에서 사용)
        window.koreanEditor = this;
    }
    
    bindEvents() {
        // 툴바 버튼 이벤트
        this.toolbar.addEventListener('click', (e) => {
            e.preventDefault();
            const button = e.target.closest('.toolbar-button');
            const select = e.target.closest('.toolbar-select');
            
            if (button) {
                this.handleToolbarCommand(button);
            } else if (select) {
                this.handleSelectChange(select);
            }
        });
        
        // 색상 팔레트 이벤트
        this.toolbar.addEventListener('click', (e) => {
            const colorItem = e.target.closest('.color-item');
            if (colorItem) {
                const palette = e.target.closest('.color-palette');
                const command = palette.id === 'textColorPalette' ? 'foreColor' : 'backColor';
                this.execCommand(command, colorItem.dataset.color);
                this.hideAllPalettes();
            }
        });
        
        // 이모지 팔레트 이벤트
        this.toolbar.addEventListener('click', (e) => {
            const emojiItem = e.target.closest('.emoji-item');
            if (emojiItem) {
                this.insertText(emojiItem.dataset.emoji);
                this.hideAllPalettes();
            }
        });
        
        // 편집 영역 이벤트
        this.editArea.addEventListener('input', () => {
            this.updateCharCount();
            this.updateOriginalTextarea();
            this.updateToolbarState();
        });
        
        this.editArea.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
        
        this.editArea.addEventListener('paste', (e) => {
            this.handlePaste(e);
        });
        
        // 선택 영역 변경 이벤트
        document.addEventListener('selectionchange', () => {
            if (this.editArea && this.editArea.contains(document.getSelection().anchorNode)) {
                this.updateToolbarState();
            }
        });
        
        // 외부 클릭으로 팔레트 숨기기
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.toolbar-dropdown')) {
                this.hideAllPalettes();
            }
        });
        
        // 드래그 앤 드롭 이미지 업로드
        this.editArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.editArea.classList.add('drag-over');
        });
        
        this.editArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            this.editArea.classList.remove('drag-over');
        });
        
        this.editArea.addEventListener('drop', (e) => {
            e.preventDefault();
            this.editArea.classList.remove('drag-over');
            this.handleFileDrop(e);
        });
        
        // 이미지 클릭 이벤트 (선택)
        this.editArea.addEventListener('click', (e) => {
            if (e.target.tagName === 'IMG' || e.target.tagName === 'VIDEO' || e.target.tagName === 'AUDIO') {
                this.selectMedia(e.target);
                e.preventDefault();
            } else {
                this.deselectMedia();
            }
        });
        
        // 이미지 우클릭 컨텍스트 메뉴
        this.editArea.addEventListener('contextmenu', (e) => {
            if (e.target.tagName === 'IMG') {
                e.preventDefault();
                this.selectImage(e.target);
                this.showImageContextMenu(e.pageX, e.pageY);
            }
        });
        
        // 이미지 컨텍스트 메뉴 클릭
        this.imageContextMenu.addEventListener('click', (e) => {
            const item = e.target.closest('.context-menu-item');
            if (item && this.selectedImage) {
                this.handleImageContextAction(item.dataset.action);
                this.hideImageContextMenu();
            }
        });
        
        // 이미지 크기 프리셋 버튼
        this.imageSizeDialog.addEventListener('click', (e) => {
            const preset = e.target.closest('.size-preset-btn');
            if (preset) {
                const width = preset.dataset.width;
                const height = preset.dataset.height;
                this.imageSizeDialog.querySelector('#imageWidth').value = width.replace('px', '');
                if (height !== 'auto') {
                    this.imageSizeDialog.querySelector('#imageHeight').value = height.replace('px', '');
                }
            }
        });
        
        // 비율 유지 체크박스
        this.imageSizeDialog.querySelector('#maintainRatio').addEventListener('change', (e) => {
            if (e.target.checked && this.selectedImage) {
                this.updateImageRatio();
            }
        });
        
        // 너비 입력시 비율 계산
        this.imageSizeDialog.querySelector('#imageWidth').addEventListener('input', () => {
            if (this.imageSizeDialog.querySelector('#maintainRatio').checked) {
                this.updateImageRatio('width');
            }
        });
        
        // 높이 입력시 비율 계산
        this.imageSizeDialog.querySelector('#imageHeight').addEventListener('input', () => {
            if (this.imageSizeDialog.querySelector('#maintainRatio').checked) {
                this.updateImageRatio('height');
            }
        });
        
        // 미디어 드래그 시작
        this.editArea.addEventListener('dragstart', (e) => {
            if (e.target.tagName === 'IMG' || e.target.tagName === 'VIDEO' || e.target.tagName === 'AUDIO') {
                this.startMediaDrag(e);
            }
        });
        
        // 드래그 오버 (드롭 존 표시)
        this.editArea.addEventListener('dragover', (e) => {
            if (this.dragging) {
                e.preventDefault();
                e.stopPropagation();
                this.showDropZone(e);
            }
        });
        
        // 드래그 리브 (드롭 존 숨김)
        this.editArea.addEventListener('dragleave', (e) => {
            if (this.dragging && !this.editArea.contains(e.relatedTarget)) {
                this.hideDropZone();
            }
        });
        
        // 드롭 (미디어 이동)
        this.editArea.addEventListener('drop', (e) => {
            if (this.dragging) {
                e.preventDefault();
                e.stopPropagation();
                this.handleMediaDrop(e);
            }
        });
        
        // 드래그 종료
        this.editArea.addEventListener('dragend', (e) => {
            this.endMediaDrag();
        });
    }
    
    handleToolbarCommand(button) {
        const command = button.dataset.command;
        
        // 특별한 처리가 필요한 명령들
        switch (command) {
            case 'createLink':
                this.showLinkDialog();
                break;
            case 'insertImage':
                this.showImageDialog();
                break;
            case 'insertVideo':
                this.showVideoDialog();
                break;
            case 'insertAudio':
                this.showAudioDialog();
                break;
            case 'insertTable':
                this.insertTable();
                break;
            case 'foreColor':
            case 'backColor':
                this.toggleColorPalette(command);
                break;
            case 'insertEmoji':
                this.toggleEmojiPalette();
                break;
            default:
                this.execCommand(command);
        }
        
        this.editArea.focus();
    }
    
    handleSelectChange(select) {
        const command = select.dataset.command;
        const value = select.value;
        
        if (value) {
            if (command === 'fontName') {
                // 글꼴 적용
                this.execCommand(command, value);
            } else {
                this.execCommand(command, value);
            }
            
            if (command === 'formatBlock' || command === 'fontSize') {
                select.value = '';
            }
        }
        
        this.editArea.focus();
    }
    
    execCommand(command, value = null) {
        // 정렬 명령어들은 CSS 클래스로 처리하여 세로 텍스트 문제 방지
        if (command.startsWith('justify')) {
            this.handleTextAlignment(command);
            return;
        }
        
        document.execCommand(command, false, value);
        this.updateToolbarState();
        this.updateOriginalTextarea();
    }
    
    handleTextAlignment(command) {
        const selection = window.getSelection();
        if (selection.rangeCount === 0) return;
        
        // 현재 선택된 요소 또는 부모 블록 요소 찾기
        let element = selection.anchorNode;
        if (element.nodeType === Node.TEXT_NODE) {
            element = element.parentElement;
        }
        
        // 블록 요소까지 올라가기
        while (element && !this.isBlockElement(element) && element !== this.editArea) {
            element = element.parentElement;
        }
        
        if (!element || element === this.editArea) {
            // 선택된 텍스트를 div로 감싸기
            const range = selection.getRangeAt(0);
            const content = range.extractContents();
            const div = document.createElement('div');
            div.appendChild(content);
            range.insertNode(div);
            element = div;
            
            // 선택 영역 복원
            selection.removeAllRanges();
            const newRange = document.createRange();
            newRange.selectNodeContents(div);
            selection.addRange(newRange);
        }
        
        // 기존 정렬 클래스 제거
        element.classList.remove('text-align-left', 'text-align-center', 'text-align-right', 'text-align-justify');
        
        // 새 정렬 클래스 추가
        switch (command) {
            case 'justifyLeft':
                element.classList.add('text-align-left');
                break;
            case 'justifyCenter':
                element.classList.add('text-align-center');
                break;
            case 'justifyRight':
                element.classList.add('text-align-right');
                break;
            case 'justifyFull':
                element.classList.add('text-align-justify');
                break;
        }
        
        this.updateToolbarState();
        this.updateOriginalTextarea();
    }
    
    isBlockElement(element) {
        const blockElements = ['DIV', 'P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'LI', 'UL', 'OL'];
        return blockElements.includes(element.tagName);
    }
    
    insertText(text) {
        const selection = window.getSelection();
        if (selection.rangeCount === 0) return;
        
        const range = selection.getRangeAt(0);
        const textNode = document.createTextNode(text);
        range.insertNode(textNode);
        range.setStartAfter(textNode);
        range.setEndAfter(textNode);
        selection.removeAllRanges();
        selection.addRange(range);
        this.updateOriginalTextarea();
    }
    
    toggleColorPalette(command) {
        const paletteId = command === 'foreColor' ? 'textColorPalette' : 'bgColorPalette';
        const palette = this.toolbar.querySelector('#' + paletteId);
        const isVisible = palette.classList.contains('show');
        
        this.hideAllPalettes();
        
        if (!isVisible) {
            palette.classList.add('show');
        }
    }
    
    toggleEmojiPalette() {
        const palette = this.toolbar.querySelector('#emojiPalette');
        const isVisible = palette.classList.contains('show');
        
        this.hideAllPalettes();
        
        if (!isVisible) {
            palette.classList.add('show');
        }
    }
    
    hideAllPalettes() {
        const palettes = this.toolbar.querySelectorAll('.color-palette, .emoji-palette');
        palettes.forEach(palette => palette.classList.remove('show'));
    }
    
    showLinkDialog() {
        const selection = window.getSelection();
        const selectedText = selection.toString();
        
        this.linkDialog.querySelector('#linkText').value = selectedText;
        this.linkDialog.querySelector('#linkUrl').value = '';
        
        this.linkOverlay.classList.add('show');
        this.linkDialog.classList.add('show');
        
        setTimeout(() => {
            this.linkDialog.querySelector('#linkUrl').focus();
        }, 100);
    }
    
    insertLink() {
        const linkText = this.linkDialog.querySelector('#linkText').value;
        const linkUrl = this.linkDialog.querySelector('#linkUrl').value;
        
        if (!linkUrl) {
            alert('링크 URL을 입력해주세요.');
            return;
        }
        
        if (linkText) {
            const selection = window.getSelection();
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                range.deleteContents();
                const link = document.createElement('a');
                link.href = linkUrl;
                link.textContent = linkText;
                link.target = '_blank';
                range.insertNode(link);
            }
        } else {
            this.execCommand('createLink', linkUrl);
        }
        
        this.cancelLink();
        this.updateOriginalTextarea();
    }
    
    cancelLink() {
        this.linkOverlay.classList.remove('show');
        this.linkDialog.classList.remove('show');
        this.editArea.focus();
    }
    
    // 모든 다이얼로그 숨기기
    hideAllDialogs() {
        this.linkOverlay.classList.remove('show');
        this.linkDialog.classList.remove('show');
        this.imageSizeDialog.classList.remove('show');
        this.hideImageContextMenu();
    }
    
    showImageDialog() {
        console.log('이미지 다이얼로그 열기');

        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.multiple = true;

        input.onchange = (e) => {
            const files = Array.from(e.target.files);
            console.log('파일 선택됨:', files.length, '개');

            if (files.length === 0) {
                console.log('선택된 파일이 없습니다.');
                return;
            }

            files.forEach((file, index) => {
                console.log(`선택된 파일 ${index + 1}:`, file.name, file.type, file.size, 'bytes');
                this.uploadImage(file);
            });
        };

        input.onerror = (e) => {
            console.error('파일 선택 오류:', e);
            alert('파일 선택 중 오류가 발생했습니다.');
        };

        // 파일 선택 다이얼로그 열기
        try {
            input.click();
            console.log('파일 선택 다이얼로그 열림');
        } catch (error) {
            console.error('파일 다이얼로그 열기 실패:', error);
            alert('파일 선택 다이얼로그를 열 수 없습니다.');
        }
    }
    
    async uploadImage(file) {
        if (!file.type.startsWith('image/')) {
            alert('이미지 파일만 업로드 가능합니다.');
            return;
        }

        console.log('이미지 업로드 시작:', file.name, file.size, 'bytes');

        // 이미지 리사이즈 (파일 크기가 1.5MB 이상인 경우)
        if (file.size > 1.5 * 1024 * 1024 && window.defaultImageResizer) {
            try {
                console.log('파일 크기가 큽니다. 리사이즈를 시도합니다...');
                file = await window.defaultImageResizer.resizeImage(file);
                console.log('리사이즈 완료:', file.size, 'bytes');
            } catch (error) {
                console.warn('이미지 리사이즈 실패, 원본 사용:', error);
            }
        }

        const formData = new FormData();
        formData.append('image', file);

        try {
            this.setLoading(true);

            console.log('업로드 URL:', this.options.imageUploadUrl);

            const response = await fetch(this.options.imageUploadUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin' // 쿠키 포함하여 인증 유지
            });

            console.log('응답 상태:', response.status, response.statusText);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            console.log('업로드 결과:', result);

            if (result.success || result.uploaded === 1) {
                // 다양한 응답 형식 지원
                const imageUrl = result.url || result.location || result.src;
                if (imageUrl) {
                    this.insertImage(imageUrl);
                    console.log('이미지 삽입 완료:', imageUrl);
                } else {
                    throw new Error('업로드는 성공했지만 이미지 URL이 없습니다.');
                }
            } else {
                const errorMsg = result.error || result.message || '알 수 없는 오류가 발생했습니다.';
                throw new Error(errorMsg);
            }
        } catch (error) {
            console.error('이미지 업로드 오류:', error);

            // 개발자 모드에서는 자세한 정보 표시
            if (console.table && error.response) {
                console.table(error.response);
            }

            // 사용자에게 친화적인 오류 메시지
            let userMessage = '이미지 업로드에 실패했습니다.';
            if (error.message.includes('인증') || error.message.includes('권한')) {
                userMessage += '\n관리자 권한으로 로그인해 주세요.';
            } else if (error.message.includes('크기') || error.message.includes('너무 큽니다')) {
                userMessage += '\n파일 크기가 너무 큽니다. (최대 2MB)\n다음 방법을 시도해보세요:\n• 이미지 크기를 줄여주세요\n• JPG 형식으로 저장해보세요\n• 이미지 압축 도구를 사용해보세요';
            } else if (error.message.includes('형식')) {
                userMessage += '\n지원하지 않는 파일 형식입니다.\nJPG, PNG, GIF, WebP 형식만 지원됩니다.';
            } else {
                userMessage += '\n' + error.message;
            }

            alert(userMessage);
        } finally {
            this.setLoading(false);
        }
    }
    
    insertImage(src) {
        console.log('이미지 삽입 시작:', src);

        const img = document.createElement('img');
        img.src = src;
        img.alt = '';
        img.style.maxWidth = '100%';
        img.style.height = 'auto';
        img.style.cursor = 'pointer';

        // 이미지 로드 에러 처리
        img.onerror = () => {
            console.error('이미지 로드 실패:', src);
            img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPuydtOuvuOyngCDroZzrk5zsmKTrpZw8L3RleHQ+PC9zdmc+';
            img.alt = '이미지 로드 실패';
        };

        try {
            const selection = window.getSelection();

            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                range.insertNode(img);

                // 커서를 이미지 다음으로 이동
                range.setStartAfter(img);
                range.setEndAfter(img);
                selection.removeAllRanges();
                selection.addRange(range);

                console.log('이미지가 에디터에 삽입되었습니다.');
            } else {
                // 선택 영역이 없는 경우 에디터 끝에 추가
                this.editArea.appendChild(img);
                console.log('이미지가 에디터 끝에 추가되었습니다.');
            }

            // 이미지 로드 후 자동 선택
            img.onload = () => {
                console.log('이미지 로드 완료, 선택 상태로 설정');
                this.selectImage(img);
            };

            // 즉시 업데이트
            this.updateOriginalTextarea();
            this.updateCharCount();

        } catch (error) {
            console.error('이미지 삽입 중 오류:', error);
            alert('이미지를 에디터에 삽입하는데 실패했습니다.');
        }
    }
    
    insertTable() {
        const rows = prompt('행 수를 입력하세요:', '3');
        const cols = prompt('열 수를 입력하세요:', '3');
        
        if (rows && cols && rows > 0 && cols > 0) {
            let tableHTML = '<table border="1" style="border-collapse: collapse; width: 100%;">';
            
            for (let i = 0; i < parseInt(rows); i++) {
                tableHTML += '<tr>';
                for (let j = 0; j < parseInt(cols); j++) {
                    tableHTML += i === 0 ? '<th style="padding: 8px; background: #f8f9fa;">제목</th>' : '<td style="padding: 8px;">내용</td>';
                }
                tableHTML += '</tr>';
            }
            
            tableHTML += '</table>';
            
            this.execCommand('insertHTML', tableHTML);
        }
    }
    
    handleKeyboardShortcuts(e) {
        if (e.ctrlKey || e.metaKey) {
            switch (e.key) {
                case 'b':
                    e.preventDefault();
                    this.execCommand('bold');
                    break;
                case 'i':
                    e.preventDefault();
                    this.execCommand('italic');
                    break;
                case 'u':
                    e.preventDefault();
                    this.execCommand('underline');
                    break;
                case 'z':
                    e.preventDefault();
                    this.execCommand('undo');
                    break;
                case 'y':
                    e.preventDefault();
                    this.execCommand('redo');
                    break;
            }
        }
    }
    
    handlePaste(e) {
        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        
        for (let item of items) {
            if (item.type.indexOf('image') !== -1) {
                e.preventDefault();
                const file = item.getAsFile();
                this.uploadImage(file);
                break;
            }
        }
    }
    
    handleFileDrop(e) {
        console.log('파일 드롭 이벤트 발생');
        const files = Array.from(e.dataTransfer.files);
        console.log('드롭된 파일 개수:', files.length);

        files.forEach((file, index) => {
            console.log(`파일 ${index + 1}:`, file.name, file.type, file.size, 'bytes');
            if (file.type.startsWith('image/')) {
                console.log('이미지 파일 감지, 업로드 시작');
                this.uploadImage(file);
            } else {
                console.log('이미지가 아닌 파일 무시:', file.type);
                alert(`${file.name}은(는) 이미지 파일이 아닙니다.`);
            }
        });
    }
    
    updateToolbarState() {
        const buttons = this.toolbar.querySelectorAll('.toolbar-button[data-command]');
        
        buttons.forEach(button => {
            const command = button.dataset.command;
            button.classList.remove('active');
            
            try {
                if (document.queryCommandState(command)) {
                    button.classList.add('active');
                }
            } catch (e) {
                // 지원하지 않는 명령의 경우 무시
            }
        });
    }
    
    updateCharCount() {
        const text = this.editArea.textContent || this.editArea.innerText || '';
        const charCount = text.length;
        const counter = this.statusBar.querySelector('.char-count');
        
        if (counter) {
            counter.textContent = `${charCount.toLocaleString()}자`;
        }
    }
    
    updateOriginalTextarea() {
        if (this.originalTextarea) {
            this.originalTextarea.value = this.editArea.innerHTML;
        }
    }
    
    setupFormSubmit() {
        const form = this.container.closest('form');
        if (form) {
            form.addEventListener('submit', () => {
                this.updateOriginalTextarea();
            });
        }
    }
    
    setLoading(loading) {
        if (loading) {
            this.editorContainer.classList.add('editor-loading');
        } else {
            this.editorContainer.classList.remove('editor-loading');
        }
    }
    
    // 공개 API 메서드들
    getContent() {
        return this.editArea.innerHTML;
    }
    
    setContent(html) {
        this.editArea.innerHTML = html;
        this.updateOriginalTextarea();
        this.updateCharCount();
    }
    
    getTextContent() {
        return this.editArea.textContent || this.editArea.innerText || '';
    }
    
    focus() {
        this.editArea.focus();
    }
    
    // 미디어 선택 (이미지, 비디오, 오디오)
    selectMedia(element) {
        this.deselectMedia();
        this.selectedImage = element;
        element.classList.add('selected');
        
        if (element.tagName === 'IMG') {
            this.createResizeHandles(element);
        } else {
            this.createMediaWrapper(element);
        }
    }
    
    // 미디어 선택 해제
    deselectMedia() {
        if (this.selectedImage) {
            this.selectedImage.classList.remove('selected');
            this.removeResizeHandles();
            this.removeMediaWrapper();
            this.selectedImage = null;
        }
        this.hideImageContextMenu();
    }
    
    // 이전 버전 호환성을 위한 별칭
    selectImage(img) { this.selectMedia(img); }
    deselectImage() { this.deselectMedia(); }
    
    // 리사이즈 핸들 생성
    createResizeHandles(img) {
        this.removeResizeHandles();
        
        const wrapper = document.createElement('div');
        wrapper.className = 'image-resize-wrapper selected';
        wrapper.style.position = 'relative';
        wrapper.style.display = 'inline-block';
        wrapper.style.maxWidth = '100%';
        
        // 이미지를 래퍼로 감싸기
        img.parentNode.insertBefore(wrapper, img);
        wrapper.appendChild(img);
        
        // 드래그 가능하게 설정
        img.draggable = true;
        
        // 인라인 편집 툴바 생성
        const toolbar = document.createElement('div');
        toolbar.className = 'image-inline-toolbar';
        toolbar.innerHTML = `
            <button class="inline-tool-btn" data-action="align-left" title="왼쪽 정렬">⬅️</button>
            <button class="inline-tool-btn" data-action="align-center" title="가운데 정렬">↔️</button>
            <button class="inline-tool-btn" data-action="align-right" title="오른쪽 정렬">➡️</button>
            <button class="inline-tool-btn" data-action="resize" title="크기 조절">🔧</button>
            <button class="inline-tool-btn" data-action="delete" title="삭제">🗑️</button>
        `;
        
        // 인라인 툴바 이벤트
        toolbar.addEventListener('click', (e) => {
            const btn = e.target.closest('.inline-tool-btn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                this.handleInlineToolAction(btn.dataset.action);
            }
        });
        
        wrapper.appendChild(toolbar);
        
        // 핸들 생성
        const handles = ['nw', 'ne', 'sw', 'se', 'n', 's', 'w', 'e'];
        handles.forEach(position => {
            const handle = document.createElement('div');
            handle.className = `image-resize-handle ${position}`;
            handle.addEventListener('mousedown', (e) => this.startResize(e, position));
            wrapper.appendChild(handle);
        });
        
        this.currentWrapper = wrapper;
    }
    
    // 리사이즈 핸들 제거
    removeResizeHandles() {
        if (this.currentWrapper) {
            const img = this.currentWrapper.querySelector('img');
            if (img) {
                this.currentWrapper.parentNode.insertBefore(img, this.currentWrapper);
                this.currentWrapper.remove();
            }
            this.currentWrapper = null;
        }
    }
    
    // 리사이즈 시작
    startResize(e, position) {
        e.preventDefault();
        e.stopPropagation();
        
        this.resizing = true;
        this.resizeData = {
            startX: e.clientX,
            startY: e.clientY,
            startWidth: this.selectedImage.offsetWidth,
            startHeight: this.selectedImage.offsetHeight,
            position: position
        };
        
        document.addEventListener('mousemove', this.handleResize.bind(this));
        document.addEventListener('mouseup', this.stopResize.bind(this));
        document.body.style.userSelect = 'none';
    }
    
    // 리사이즈 처리
    handleResize(e) {
        if (!this.resizing || !this.selectedImage) return;
        
        const deltaX = e.clientX - this.resizeData.startX;
        const deltaY = e.clientY - this.resizeData.startY;
        
        let newWidth = this.resizeData.startWidth;
        let newHeight = this.resizeData.startHeight;
        
        const position = this.resizeData.position;
        
        if (position.includes('e')) newWidth += deltaX;
        if (position.includes('w')) newWidth -= deltaX;
        if (position.includes('s')) newHeight += deltaY;
        if (position.includes('n')) newHeight -= deltaY;
        
        // 최소 크기 제한
        newWidth = Math.max(50, newWidth);
        newHeight = Math.max(50, newHeight);
        
        // 비율 유지 (Shift 키를 누르고 있을 때 또는 모서리 핸들)
        if (e.shiftKey || ['nw', 'ne', 'sw', 'se'].includes(position)) {
            const aspectRatio = this.resizeData.startWidth / this.resizeData.startHeight;
            if (position.includes('e') || position.includes('w')) {
                newHeight = newWidth / aspectRatio;
            } else {
                newWidth = newHeight * aspectRatio;
            }
        }
        
        this.selectedImage.style.width = newWidth + 'px';
        this.selectedImage.style.height = newHeight + 'px';
    }
    
    // 리사이즈 종료
    stopResize() {
        this.resizing = false;
        document.removeEventListener('mousemove', this.handleResize.bind(this));
        document.removeEventListener('mouseup', this.stopResize.bind(this));
        document.body.style.userSelect = '';
        this.updateOriginalTextarea();
    }
    
    // 이미지 컨텍스트 메뉴 표시
    showImageContextMenu(x, y) {
        this.imageContextMenu.style.left = x + 'px';
        this.imageContextMenu.style.top = y + 'px';
        this.imageContextMenu.classList.add('show');
    }
    
    // 이미지 컨텍스트 메뉴 숨기기
    hideImageContextMenu() {
        this.imageContextMenu.classList.remove('show');
    }
    
    // 이미지 컨텍스트 메뉴 액션 처리
    handleImageContextAction(action) {
        if (!this.selectedImage) return;
        
        switch (action) {
            case 'resize':
                this.showImageSizeDialog();
                break;
            case 'align-left':
                this.selectedImage.style.float = 'left';
                this.selectedImage.style.margin = '0 20px 20px 0';
                break;
            case 'align-center':
                this.selectedImage.style.float = 'none';
                this.selectedImage.style.margin = '20px auto';
                this.selectedImage.style.display = 'block';
                break;
            case 'align-right':
                this.selectedImage.style.float = 'right';
                this.selectedImage.style.margin = '0 0 20px 20px';
                break;
            case 'alt-text':
                const altText = prompt('대체 텍스트를 입력하세요:', this.selectedImage.alt);
                if (altText !== null) {
                    this.selectedImage.alt = altText;
                }
                break;
            case 'delete':
                if (confirm('이미지를 삭제하시겠습니까?')) {
                    this.selectedImage.remove();
                    this.deselectImage();
                }
                break;
        }
        this.updateOriginalTextarea();
    }
    
    // 이미지 크기 다이얼로그 표시
    showImageSizeDialog() {
        if (!this.selectedImage) return;
        
        const width = parseInt(this.selectedImage.offsetWidth);
        const height = parseInt(this.selectedImage.offsetHeight);
        
        this.imageSizeDialog.querySelector('#imageWidth').value = width;
        this.imageSizeDialog.querySelector('#imageHeight').value = height;
        
        this.linkOverlay.classList.add('show');
        this.imageSizeDialog.classList.add('show');
    }
    
    // 이미지 크기 적용
    applyImageSize() {
        if (!this.selectedImage) return;
        
        const widthInput = this.imageSizeDialog.querySelector('#imageWidth');
        const heightInput = this.imageSizeDialog.querySelector('#imageHeight');
        
        const width = widthInput.value;
        const height = heightInput.value;
        
        if (width) {
            if (width.includes('%')) {
                this.selectedImage.style.width = width;
            } else {
                this.selectedImage.style.width = width + 'px';
            }
        }
        
        if (height && height !== 'auto') {
            this.selectedImage.style.height = height + 'px';
        } else {
            this.selectedImage.style.height = 'auto';
        }
        
        this.cancelImageSize();
        this.updateOriginalTextarea();
    }
    
    // 이미지 크기 다이얼로그 취소
    cancelImageSize() {
        this.linkOverlay.classList.remove('show');
        this.imageSizeDialog.classList.remove('show');
    }
    
    // 이미지 비율 업데이트
    updateImageRatio(changedDimension = 'width') {
        if (!this.selectedImage) return;
        
        const widthInput = this.imageSizeDialog.querySelector('#imageWidth');
        const heightInput = this.imageSizeDialog.querySelector('#imageHeight');
        
        const originalWidth = this.selectedImage.naturalWidth;
        const originalHeight = this.selectedImage.naturalHeight;
        const aspectRatio = originalWidth / originalHeight;
        
        if (changedDimension === 'width' && widthInput.value) {
            const newHeight = Math.round(widthInput.value / aspectRatio);
            heightInput.value = newHeight;
        } else if (changedDimension === 'height' && heightInput.value) {
            const newWidth = Math.round(heightInput.value * aspectRatio);
            widthInput.value = newWidth;
        }
    }
    
    // 미디어 드래그 시작
    startMediaDrag(e) {
        this.dragging = true;
        this.dragData = {
            element: e.target,
            offsetX: e.offsetX,
            offsetY: e.offsetY
        };
        e.target.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    }
    
    // 드래그 종료
    endMediaDrag() {
        if (this.dragging) {
            this.dragData.element.classList.remove('dragging');
            this.hideDropZone();
            this.dragging = false;
            this.dragData = null;
        }
    }
    
    // 드롭 존 표시
    showDropZone(e) {
        this.hideDropZone();
        
        const rect = this.editArea.getBoundingClientRect();
        const y = e.clientY - rect.top;
        const children = Array.from(this.editArea.children);
        
        let insertBefore = null;
        for (let child of children) {
            const childRect = child.getBoundingClientRect();
            const childY = childRect.top - rect.top + childRect.height / 2;
            if (y < childY) {
                insertBefore = child;
                break;
            }
        }
        
        const dropZone = document.createElement('div');
        dropZone.className = 'drop-zone';
        dropZone.id = 'media-drop-zone';
        
        if (insertBefore) {
            this.editArea.insertBefore(dropZone, insertBefore);
        } else {
            this.editArea.appendChild(dropZone);
        }
    }
    
    // 드롭 존 숨김
    hideDropZone() {
        const dropZone = document.getElementById('media-drop-zone');
        if (dropZone) {
            dropZone.remove();
        }
    }
    
    // 미디어 드롭 처리
    handleMediaDrop(e) {
        if (!this.dragData) return;
        
        const dropZone = document.getElementById('media-drop-zone');
        if (dropZone) {
            dropZone.parentNode.insertBefore(this.dragData.element, dropZone);
            dropZone.remove();
        }
        
        this.updateOriginalTextarea();
        this.endMediaDrag();
    }
    
    // 미디어 래퍼 생성 (비디오, 오디오용)
    createMediaWrapper(element) {
        this.removeMediaWrapper();
        
        const wrapper = document.createElement('div');
        wrapper.className = 'media-wrapper selected';
        wrapper.style.position = 'relative';
        wrapper.style.display = 'inline-block';
        wrapper.style.maxWidth = '100%';
        
        // 미디어를 래퍼로 감싸기
        element.parentNode.insertBefore(wrapper, element);
        wrapper.appendChild(element);
        
        // 미디어 컨트롤 생성
        const controls = document.createElement('div');
        controls.className = 'media-controls';
        controls.innerHTML = `
            <button class="media-control-btn" data-action="align-left" title="왼쪽 정렬">⬅️</button>
            <button class="media-control-btn" data-action="align-center" title="가운데 정렬">↔️</button>
            <button class="media-control-btn" data-action="align-right" title="오른쪽 정렬">➡️</button>
            <button class="media-control-btn" data-action="delete" title="삭제">🗑️</button>
        `;
        
        // 미디어 컨트롤 이벤트
        controls.addEventListener('click', (e) => {
            const btn = e.target.closest('.media-control-btn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                this.handleMediaControlAction(btn.dataset.action);
            }
        });
        
        wrapper.appendChild(controls);
        this.currentMediaWrapper = wrapper;
    }
    
    // 미디어 래퍼 제거
    removeMediaWrapper() {
        if (this.currentMediaWrapper) {
            const media = this.currentMediaWrapper.querySelector('video, audio');
            if (media) {
                this.currentMediaWrapper.parentNode.insertBefore(media, this.currentMediaWrapper);
                this.currentMediaWrapper.remove();
            }
            this.currentMediaWrapper = null;
        }
    }
    
    // 인라인 툴 액션 처리
    handleInlineToolAction(action) {
        if (!this.selectedImage) return;
        
        switch (action) {
            case 'align-left':
                this.selectedImage.className = this.selectedImage.className.replace(/align-\w+/g, '') + ' align-left';
                break;
            case 'align-center':
                this.selectedImage.className = this.selectedImage.className.replace(/align-\w+/g, '') + ' align-center';
                break;
            case 'align-right':
                this.selectedImage.className = this.selectedImage.className.replace(/align-\w+/g, '') + ' align-right';
                break;
            case 'resize':
                this.showImageSizeDialog();
                break;
            case 'delete':
                if (confirm('이 항목을 삭제하시겠습니까?')) {
                    this.selectedImage.remove();
                    this.deselectMedia();
                }
                break;
        }
        this.updateOriginalTextarea();
    }
    
    // 미디어 컨트롤 액션 처리
    handleMediaControlAction(action) {
        this.handleInlineToolAction(action);
    }
    
    // 비디오 다이얼로그 표시
    showVideoDialog() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'video/*';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                this.uploadMedia(file, 'video');
            }
        };
        input.click();
    }
    
    // 오디오 다이얼로그 표시
    showAudioDialog() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'audio/*';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                this.uploadMedia(file, 'audio');
            }
        };
        input.click();
    }
    
    // 미디어 업로드
    async uploadMedia(file, type) {
        if (!file.type.startsWith(type + '/')) {
            alert(type === 'video' ? '비디오 파일만 업로드 가능합니다.' : '오디오 파일만 업로드 가능합니다.');
            return;
        }
        
        // 큰 파일 크기 제한 (100MB)
        const maxSize = 100 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('파일 크기가 너무 큽니다. 최대 100MB까지 업로드 가능합니다.');
            return;
        }
        
        const formData = new FormData();
        formData.append('media', file);
        formData.append('type', type);
        
        try {
            this.setLoading(true);
            
            const response = await fetch(this.options.imageUploadUrl.replace('image_upload', 'media_upload'), {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.insertMedia(result.url, type);
            } else {
                alert('미디어 업로드에 실패했습니다: ' + result.message);
            }
        } catch (error) {
            console.error('Media upload error:', error);
            alert('미디어 업로드 중 오류가 발생했습니다.');
        } finally {
            this.setLoading(false);
        }
    }
    
    // 미디어 삽입
    insertMedia(src, type) {
        const element = document.createElement(type);
        element.src = src;
        element.controls = true;
        element.style.maxWidth = '100%';
        
        if (type === 'video') {
            element.style.maxHeight = '400px';
        }
        
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
            const range = selection.getRangeAt(0);
            range.insertNode(element);
            
            // 로드 후 자동 선택
            element.onloadedmetadata = () => {
                this.selectMedia(element);
            };
        }
        
        this.updateOriginalTextarea();
    }
    
    destroy() {
        if (this.originalTextarea) {
            this.originalTextarea.style.display = '';
        }
        
        if (this.editorContainer) {
            this.editorContainer.remove();
        }
        
        if (this.linkDialog) {
            this.linkDialog.remove();
        }
        
        if (this.imageSizeDialog) {
            this.imageSizeDialog.remove();
        }
        
        if (this.imageContextMenu) {
            this.imageContextMenu.remove();
        }
        
        if (this.linkOverlay) {
            this.linkOverlay.remove();
        }
        
        delete window.koreanEditor;
    }
}

// 전역 초기화 함수
function initKoreanEditor(container, options = {}) {
    return new KoreanEditor(container, options);
}

// 기존 textarea를 자동으로 에디터로 변환하는 함수
function autoInitKoreanEditor() {
    const textareas = document.querySelectorAll('textarea[data-korean-editor]');
    const editors = [];
    
    textareas.forEach(textarea => {
        const options = {
            height: textarea.dataset.height || '400px',
            placeholder: textarea.placeholder || '내용을 입력하세요...',
            imageUploadUrl: textarea.dataset.uploadUrl || '/admin/api/image_upload.php'
        };
        
        const container = textarea.parentElement;
        const editor = new KoreanEditor(container, options);
        editors.push(editor);
    });
    
    return editors;
}

// DOM 로드 완료시 자동 초기화
document.addEventListener('DOMContentLoaded', function() {
    autoInitKoreanEditor();
});

// 모듈로 내보내기
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { KoreanEditor, initKoreanEditor };
}