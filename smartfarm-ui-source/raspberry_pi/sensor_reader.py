#!/usr/bin/env python3
"""
탄생 스마트팜 - Raspberry Pi 센서 데이터 수집 프로그램
온도, 습도, 광량, pH, EC 센서 연동
"""

import os
import sys
import time
import json
import requests
import random
from datetime import datetime
import argparse

# 실제 센서가 연결되면 아래 라이브러리들을 import
# import Adafruit_DHT  # DHT 온습도 센서
# import board
# import busio
# import adafruit_ads1x15.ads1115 as ADS
# from adafruit_ads1x15.analog_in import AnalogIn

class SensorReader:
    def __init__(self, server_url, api_key, raspberry_id="rpi_001"):
        self.server_url = server_url.rstrip('/')
        self.api_key = api_key
        self.raspberry_id = raspberry_id
        self.sensors_available = self.check_sensors()
        
    def check_sensors(self):
        """연결된 센서 확인"""
        # 실제 하드웨어에서는 센서 연결 상태를 확인
        # 현재는 시뮬레이션 모드
        return {
            'dht22': False,      # 온습도 센서
            'bh1750': False,     # 광량 센서  
            'ph_sensor': False,  # pH 센서
            'ec_sensor': False,  # EC 센서
            'soil_moisture': False  # 토양수분 센서
        }
    
    def read_temperature_humidity(self):
        """온습도 센서 읽기 (DHT22)"""
        if self.sensors_available['dht22']:
            # 실제 DHT22 센서 읽기
            # humidity, temperature = Adafruit_DHT.read_retry(Adafruit_DHT.DHT22, 4)
            # return temperature, humidity
            pass
        
        # 시뮬레이션 데이터
        temperature = round(random.uniform(18.0, 28.0), 1)
        humidity = round(random.uniform(40.0, 80.0), 1)
        return temperature, humidity
    
    def read_light_intensity(self):
        """광량 센서 읽기 (BH1750)"""
        if self.sensors_available['bh1750']:
            # 실제 BH1750 센서 읽기
            pass
        
        # 시뮬레이션 데이터 (lux)
        current_hour = datetime.now().hour
        if 6 <= current_hour <= 18:  # 낮시간
            light = round(random.uniform(1000, 5000), 0)
        else:  # 밤시간
            light = round(random.uniform(0, 100), 0)
        return light
    
    def read_ph_level(self):
        """pH 센서 읽기"""
        if self.sensors_available['ph_sensor']:
            # 실제 pH 센서 읽기 (ADC를 통한 아날로그 값)
            pass
        
        # 시뮬레이션 데이터
        ph = round(random.uniform(5.5, 7.5), 1)
        return ph
    
    def read_ec_level(self):
        """EC 센서 읽기 (전기전도도)"""
        if self.sensors_available['ec_sensor']:
            # 실제 EC 센서 읽기 (ADC를 통한 아날로그 값)
            pass
        
        # 시뮬레이션 데이터 (µS/cm)
        ec = round(random.uniform(800, 2000), 1)
        return ec
    
    def read_soil_moisture(self):
        """토양수분 센서 읽기"""
        if self.sensors_available['soil_moisture']:
            # 실제 토양수분 센서 읽기
            pass
        
        # 시뮬레이션 데이터 (%)
        moisture = round(random.uniform(30.0, 90.0), 1)
        return moisture
    
    def read_all_sensors(self):
        """모든 센서 데이터 읽기"""
        try:
            print("센서 데이터 읽는 중...")
            
            # 각 센서에서 데이터 읽기
            temperature, humidity = self.read_temperature_humidity()
            light_intensity = self.read_light_intensity()
            ph_level = self.read_ph_level()
            ec_level = self.read_ec_level()
            soil_moisture = self.read_soil_moisture()
            
            sensor_data = {
                'raspberry_id': self.raspberry_id,
                'temperature': temperature,
                'humidity': humidity,
                'light_intensity': light_intensity,
                'ph_level': ph_level,
                'ec_level': ec_level,
                'soil_moisture': soil_moisture,
                'timestamp': datetime.now().isoformat()
            }
            
            print(f"센서 데이터:")
            print(f"  온도: {temperature}°C")
            print(f"  습도: {humidity}%")
            print(f"  광량: {light_intensity} lux")
            print(f"  pH: {ph_level}")
            print(f"  EC: {ec_level} µS/cm")
            print(f"  토양수분: {soil_moisture}%")
            
            return sensor_data
            
        except Exception as e:
            print(f"센서 읽기 오류: {e}")
            return None
    
    def send_to_server(self, sensor_data):
        """서버에 센서 데이터 전송"""
        try:
            url = f"{self.server_url}/api/raspberry/sensor_data.php"
            
            # API 키 추가
            sensor_data['api_key'] = self.api_key
            
            print(f"서버에 데이터 전송 중: {url}")
            response = requests.post(url, data=sensor_data, timeout=30)
            
            if response.status_code == 200:
                result = response.json()
                if result.get('success'):
                    print(f"전송 성공: {result.get('message')}")
                    return True
                else:
                    print(f"전송 실패: {result.get('message')}")
                    return False
            else:
                print(f"서버 오류: HTTP {response.status_code}")
                return False
                
        except Exception as e:
            print(f"서버 전송 오류: {e}")
            return False
    
    def save_local_backup(self, sensor_data):
        """로컬에 백업 저장"""
        try:
            backup_dir = "/tmp/sensor_backup"
            os.makedirs(backup_dir, exist_ok=True)
            
            date_str = datetime.now().strftime("%Y%m%d")
            backup_file = f"{backup_dir}/sensor_data_{date_str}.json"
            
            # 기존 데이터 읽기
            data_list = []
            if os.path.exists(backup_file):
                with open(backup_file, 'r') as f:
                    data_list = json.load(f)
            
            # 새 데이터 추가
            data_list.append(sensor_data)
            
            # 파일에 저장
            with open(backup_file, 'w') as f:
                json.dump(data_list, f, indent=2, ensure_ascii=False)
            
            print(f"로컬 백업 저장: {backup_file}")
            return True
            
        except Exception as e:
            print(f"로컬 백업 실패: {e}")
            return False
    
    def start_monitoring(self, interval_minutes=5):
        """센서 모니터링 시작"""
        print(f"센서 모니터링 시작 - {interval_minutes}분 간격")
        print(f"라즈베리파이 ID: {self.raspberry_id}")
        
        try:
            while True:
                print(f"\n=== {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} ===")
                
                # 센서 데이터 읽기
                sensor_data = self.read_all_sensors()
                
                if sensor_data:
                    # 서버에 전송 시도
                    if not self.send_to_server(sensor_data):
                        # 전송 실패시 로컬 백업
                        self.save_local_backup(sensor_data)
                
                print(f"{interval_minutes}분 대기 중...")
                time.sleep(interval_minutes * 60)
                
        except KeyboardInterrupt:
            print("\n센서 모니터링 중단됨")
        except Exception as e:
            print(f"모니터링 오류: {e}")
    
    def calibrate_sensors(self):
        """센서 보정"""
        print("센서 보정 시작...")
        
        # pH 센서 보정 (pH 4.0, 7.0 용액 사용)
        print("pH 센서를 pH 7.0 용액에 담그고 Enter를 누르세요...")
        input()
        ph_7_reading = self.read_ph_level()
        print(f"pH 7.0 측정값: {ph_7_reading}")
        
        print("pH 센서를 pH 4.0 용액에 담그고 Enter를 누르세요...")
        input()
        ph_4_reading = self.read_ph_level()
        print(f"pH 4.0 측정값: {ph_4_reading}")
        
        # EC 센서 보정도 유사하게 구현
        print("센서 보정 완료")
    
    def test_connection(self):
        """서버 연결 테스트"""
        try:
            # 테스트용 더미 데이터
            test_data = {
                'raspberry_id': self.raspberry_id,
                'temperature': 25.0,
                'humidity': 60.0,
                'light_intensity': 1000,
                'ph_level': 6.5,
                'ec_level': 1200,
                'soil_moisture': 70.0
            }
            
            return self.send_to_server(test_data)
            
        except Exception as e:
            print(f"연결 테스트 실패: {e}")
            return False

def main():
    parser = argparse.ArgumentParser(description='탄생 스마트팜 센서 리더')
    parser.add_argument('--server', default='http://localhost:8000', help='서버 URL')
    parser.add_argument('--api-key', required=True, help='API 키')
    parser.add_argument('--raspberry-id', default='rpi_001', help='라즈베리파이 ID')
    parser.add_argument('--mode', choices=['single', 'monitor', 'test', 'calibrate'], 
                       default='single', help='실행 모드')
    parser.add_argument('--interval', type=int, default=5, help='모니터링 간격 (분)')
    
    args = parser.parse_args()
    
    # 센서 리더 생성
    sensor_reader = SensorReader(args.server, args.api_key, args.raspberry_id)
    
    try:
        if args.mode == 'single':
            # 단일 측정
            sensor_data = sensor_reader.read_all_sensors()
            if sensor_data:
                sensor_reader.send_to_server(sensor_data)
                
        elif args.mode == 'monitor':
            # 연속 모니터링
            sensor_reader.start_monitoring(args.interval)
            
        elif args.mode == 'test':
            # 연결 테스트
            sensor_reader.test_connection()
            
        elif args.mode == 'calibrate':
            # 센서 보정
            sensor_reader.calibrate_sensors()
            
    except KeyboardInterrupt:
        print("\n프로그램이 중단되었습니다")
    except Exception as e:
        print(f"오류 발생: {e}")

if __name__ == "__main__":
    main()