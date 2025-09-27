/**
 * 클라이언트 사이드 이미지 리사이즈 유틸리티
 */

class ImageResizer {
    constructor(options = {}) {
        this.maxWidth = options.maxWidth || 1200;
        this.maxHeight = options.maxHeight || 800;
        this.quality = options.quality || 0.8;
        this.maxFileSize = options.maxFileSize || 2 * 1024 * 1024; // 2MB
    }

    /**
     * 이미지 파일을 리사이즈하여 File 객체 반환
     */
    async resizeImage(file) {
        return new Promise((resolve, reject) => {
            // 이미지 파일이 아닌 경우 원본 반환
            if (!file.type.startsWith('image/')) {
                resolve(file);
                return;
            }

            // 파일 크기가 제한보다 작으면 원본 반환
            if (file.size <= this.maxFileSize) {
                resolve(file);
                return;
            }

            console.log(`이미지 리사이즈 시작: ${file.name} (${file.size} bytes)`);

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();

            img.onload = () => {
                try {
                    // 리사이즈 비율 계산
                    let { width, height } = this.calculateDimensions(img.width, img.height);

                    // 캔버스 설정
                    canvas.width = width;
                    canvas.height = height;

                    // 이미지 품질 개선을 위한 설정
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';

                    // 이미지 그리기
                    ctx.drawImage(img, 0, 0, width, height);

                    // 캔버스를 Blob으로 변환
                    canvas.toBlob((blob) => {
                        if (blob) {
                            // File 객체 생성
                            const resizedFile = new File([blob], file.name, {
                                type: file.type,
                                lastModified: Date.now()
                            });

                            console.log(`이미지 리사이즈 완료: ${resizedFile.size} bytes (${Math.round((1 - resizedFile.size / file.size) * 100)}% 감소)`);
                            resolve(resizedFile);
                        } else {
                            reject(new Error('이미지 변환에 실패했습니다.'));
                        }
                    }, file.type, this.quality);

                } catch (error) {
                    reject(error);
                }
            };

            img.onerror = () => {
                reject(new Error('이미지 로드에 실패했습니다.'));
            };

            // 이미지 로드
            img.src = URL.createObjectURL(file);
        });
    }

    /**
     * 리사이즈할 크기 계산
     */
    calculateDimensions(originalWidth, originalHeight) {
        let width = originalWidth;
        let height = originalHeight;

        // 최대 크기를 초과하는 경우 비율 유지하면서 축소
        if (width > this.maxWidth || height > this.maxHeight) {
            const widthRatio = this.maxWidth / width;
            const heightRatio = this.maxHeight / height;
            const ratio = Math.min(widthRatio, heightRatio);

            width = Math.round(width * ratio);
            height = Math.round(height * ratio);
        }

        return { width, height };
    }

    /**
     * 여러 이미지를 일괄 리사이즈
     */
    async resizeImages(files) {
        const results = [];
        for (const file of files) {
            try {
                const resizedFile = await this.resizeImage(file);
                results.push(resizedFile);
            } catch (error) {
                console.error(`이미지 리사이즈 실패: ${file.name}`, error);
                results.push(file); // 실패시 원본 반환
            }
        }
        return results;
    }

    /**
     * 이미지 정보 확인
     */
    async getImageInfo(file) {
        return new Promise((resolve) => {
            if (!file.type.startsWith('image/')) {
                resolve(null);
                return;
            }

            const img = new Image();
            img.onload = () => {
                resolve({
                    width: img.width,
                    height: img.height,
                    size: file.size,
                    type: file.type,
                    name: file.name
                });
                URL.revokeObjectURL(img.src);
            };

            img.onerror = () => {
                resolve(null);
            };

            img.src = URL.createObjectURL(file);
        });
    }
}

// 전역 인스턴스
window.ImageResizer = ImageResizer;

// 기본 리사이저 (2MB 제한에 맞춤)
window.defaultImageResizer = new ImageResizer({
    maxWidth: 1200,
    maxHeight: 800,
    quality: 0.8,
    maxFileSize: 1.8 * 1024 * 1024 // 1.8MB (여유분 고려)
});