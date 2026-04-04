#!/usr/bin/env python3
"""
탄생 스마트팜 - Raspberry Pi 카메라 제어 프로그램
Sony IMX500 AI카메라 12MP 지원
"""

import os
import sys
import time
import json
import requests
from datetime import datetime
from picamera2 import Picamera2
from libcamera import controls
import argparse

class CameraController:
    def __init__(self, server_url, api_key, raspberry_id="rpi_001"):
        self.server_url = server_url.rstrip('/')
        self.api_key = api_key
        self.raspberry_id = raspberry_id
        self.camera = None
        self.config = None
        
    def initialize_camera(self):
        """카메라 초기화"""
        try:
            self.camera = Picamera2()
            
            # IMX500 카메라 설정
            self.config = self.camera.create_preview_configuration(
                main={"size": (4056, 3040)},  # 12MP resolution
                lores={"size": (640, 480)},   # Low resolution for preview
                display="lores"
            )
            
            self.camera.configure(self.config)
            
            # 카메라 설정
            self.camera.set_controls({
                "AfMode": controls.AfModeEnum.Continuous,
                "AfSpeed": controls.AfSpeedEnum.Fast,
                "AwbEnable": True,
                "AeEnable": True,
                "Brightness": 0.0,
                "Contrast": 1.0,
                "Saturation": 1.0,
                "Sharpness": 1.0
            })
            
            self.camera.start()
            print(f"카메라 초기화 완료 - {self.raspberry_id}")
            
            # 카메라 안정화 대기
            time.sleep(2)
            return True
            
        except Exception as e:
            print(f"카메라 초기화 실패: {e}")
            return False
    
    def capture_image(self, user_id=0, quality=95):
        """이미지 촬영"""
        if not self.camera:
            raise Exception("카메라가 초기화되지 않았습니다")
        
        try:
            # 파일명 생성
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            filename = f"plant_{self.raspberry_id}_{timestamp}.jpg"
            filepath = f"/tmp/{filename}"
            
            print(f"이미지 촬영 중... {filename}")
            
            # 이미지 촬영
            self.camera.capture_file(filepath)
            
            # 이미지 품질 확인
            if os.path.exists(filepath) and os.path.getsize(filepath) > 0:
                print(f"촬영 완료: {filepath}")
                
                # 서버에 업로드
                if self.upload_image(filepath, user_id):
                    os.remove(filepath)  # 업로드 성공시 임시 파일 삭제
                    return True
                else:
                    print(f"업로드 실패, 로컬에 저장됨: {filepath}")
                    return False
            else:
                raise Exception("이미지 파일 생성 실패")
                
        except Exception as e:
            print(f"이미지 촬영 실패: {e}")
            return False
    
    def upload_image(self, filepath, user_id):
        """서버에 이미지 업로드"""
        try:
            url = f"{self.server_url}/api/raspberry/image_upload.php"
            
            with open(filepath, 'rb') as f:
                files = {'image': f}
                data = {
                    'api_key': self.api_key,
                    'user_id': user_id,
                    'raspberry_id': self.raspberry_id
                }
                
                print(f"서버에 업로드 중: {url}")
                response = requests.post(url, files=files, data=data, timeout=30)
                
                if response.status_code == 200:
                    result = response.json()
                    if result.get('success'):
                        print(f"업로드 성공: {result.get('message')}")
                        return True
                    else:
                        print(f"업로드 실패: {result.get('message')}")
                        return False
                else:
                    print(f"서버 오류: HTTP {response.status_code}")
                    return False
                    
        except Exception as e:
            print(f"업로드 오류: {e}")
            return False
    
    def start_auto_capture(self, interval_minutes=30, user_id=0):
        """자동 촬영 시작"""
        print(f"자동 촬영 시작 - {interval_minutes}분 간격")
        
        try:
            while True:
                print(f"\n=== {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} ===")
                self.capture_image(user_id)
                
                print(f"{interval_minutes}분 대기 중...")
                time.sleep(interval_minutes * 60)
                
        except KeyboardInterrupt:
            print("\n자동 촬영 중단됨")
        except Exception as e:
            print(f"자동 촬영 오류: {e}")
    
    def test_connection(self):
        """서버 연결 테스트"""
        try:
            url = f"{self.server_url}/api/raspberry/test.php"
            response = requests.get(url, timeout=10)
            
            if response.status_code == 200:
                print("서버 연결 테스트 성공")
                return True
            else:
                print(f"서버 연결 실패: HTTP {response.status_code}")
                return False
                
        except Exception as e:
            print(f"서버 연결 테스트 실패: {e}")
            return False
    
    def cleanup(self):
        """리소스 정리"""
        if self.camera:
            self.camera.stop()
            self.camera.close()
            print("카메라 리소스 정리 완료")

def main():
    parser = argparse.ArgumentParser(description='탄생 스마트팜 카메라 제어기')
    parser.add_argument('--server', default='http://localhost:8000', help='서버 URL')
    parser.add_argument('--api-key', required=True, help='API 키')
    parser.add_argument('--raspberry-id', default='rpi_001', help='라즈베리파이 ID')
    parser.add_argument('--user-id', type=int, default=0, help='사용자 ID')
    parser.add_argument('--mode', choices=['single', 'auto', 'test'], default='single', help='실행 모드')
    parser.add_argument('--interval', type=int, default=30, help='자동 촬영 간격 (분)')
    
    args = parser.parse_args()
    
    # 카메라 컨트롤러 생성
    controller = CameraController(args.server, args.api_key, args.raspberry_id)
    
    try:
        if args.mode == 'test':
            # 연결 테스트만 실행
            controller.test_connection()
        else:
            # 카메라 초기화
            if not controller.initialize_camera():
                sys.exit(1)
            
            if args.mode == 'single':
                # 단일 촬영
                controller.capture_image(args.user_id)
            elif args.mode == 'auto':
                # 자동 촬영
                controller.start_auto_capture(args.interval, args.user_id)
                
    except KeyboardInterrupt:
        print("\n프로그램이 중단되었습니다")
    except Exception as e:
        print(f"오류 발생: {e}")
    finally:
        controller.cleanup()

if __name__ == "__main__":
    main()