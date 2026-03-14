#!/usr/bin/env python3
"""
스마트팜 데몬 시스템 - 24시간 데이터 수집 및 API 폴링
작성자: Claude Code AI Assistant
날짜: 2026-01-14
"""

import asyncio
import aiohttp
import json
import sqlite3
import time
import logging
from datetime import datetime, timedelta
from pathlib import Path
import signal
import sys
import yaml

# 로깅 설정
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/var/log/smartfarm-daemon.log'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger('smartfarm-daemon')

class SmartFarmDaemon:
    def __init__(self, config_path='/home/spinmoll/config.yaml'):
        self.running = True
        self.config = self.load_config(config_path)
        self.db_path = self.config.get('database_path', '/home/spinmoll/smartfarm.db')
        self.api_endpoints = self.config.get('api_endpoints', {})
        self.polling_intervals = self.config.get('polling_intervals', {})

        # 기본값 설정
        self.sensor_polling_interval = self.polling_intervals.get('sensor_data', 30)  # 30초
        self.device_status_interval = self.polling_intervals.get('device_status', 60)  # 60초
        self.cleanup_interval = self.polling_intervals.get('cleanup', 3600)  # 1시간

        self.session = None
        self.init_database()

    def load_config(self, config_path):
        """설정 파일 로드"""
        try:
            if Path(config_path).exists():
                with open(config_path, 'r', encoding='utf-8') as f:
                    return yaml.safe_load(f)
            else:
                logger.warning(f"설정 파일이 없습니다: {config_path}. 기본값을 사용합니다.")
                return self.get_default_config()
        except Exception as e:
            logger.error(f"설정 파일 로드 실패: {e}")
            return self.get_default_config()

    def get_default_config(self):
        """기본 설정값 반환"""
        return {
            'database_path': '/home/spinmoll/smartfarm.db',
            'api_endpoints': {
                'sensor_data': 'http://localhost/api/smartfarm/get_realtime_sensor_data.php',
                'device_status': 'http://localhost/api/smartfarm/get_device_status.php',
                'environmental_data': 'http://localhost/api/smartfarm/get_sensor_data.php'
            },
            'polling_intervals': {
                'sensor_data': 30,
                'device_status': 60,
                'cleanup': 3600
            },
            'data_retention': {
                'sensor_data_days': 30,
                'device_logs_days': 7,
                'error_logs_days': 14
            }
        }

    def init_database(self):
        """데이터베이스 초기화"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()

            # 센서 데이터 테이블
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS sensor_data (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sensor_location TEXT NOT NULL,
                    temperature REAL,
                    humidity REAL,
                    ec REAL,
                    ph REAL,
                    co2 INTEGER,
                    ppfd REAL,
                    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ''')

            # 장치 상태 테이블
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS device_status (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    device_name TEXT NOT NULL,
                    device_type TEXT NOT NULL,
                    status TEXT NOT NULL,
                    value REAL,
                    unit TEXT,
                    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ''')

            # 에러 로그 테이블
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS error_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    error_type TEXT NOT NULL,
                    error_message TEXT NOT NULL,
                    stack_trace TEXT,
                    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ''')

            # 시스템 상태 테이블
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS system_status (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    daemon_status TEXT NOT NULL,
                    last_sensor_update TIMESTAMP,
                    last_device_update TIMESTAMP,
                    total_records INTEGER DEFAULT 0,
                    uptime_seconds INTEGER DEFAULT 0,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ''')

            # 인덱스 생성
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_sensor_location_time ON sensor_data(sensor_location, recorded_at)')
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_device_time ON device_status(device_name, recorded_at)')
            cursor.execute('CREATE INDEX IF NOT EXISTS idx_error_time ON error_logs(recorded_at)')

            conn.commit()
            conn.close()
            logger.info("데이터베이스 초기화 완료")

        except Exception as e:
            logger.error(f"데이터베이스 초기화 실패: {e}")
            raise

    async def start(self):
        """데몬 시작"""
        logger.info("스마트팜 데몬 시작")

        # HTTP 세션 생성
        timeout = aiohttp.ClientTimeout(total=30)
        self.session = aiohttp.ClientSession(timeout=timeout)

        try:
            # 시스템 상태 초기화
            await self.update_system_status("running")

            # 병렬 작업 시작
            tasks = [
                asyncio.create_task(self.sensor_data_loop()),
                asyncio.create_task(self.device_status_loop()),
                asyncio.create_task(self.cleanup_loop()),
                asyncio.create_task(self.system_monitor_loop())
            ]

            await asyncio.gather(*tasks)

        except Exception as e:
            logger.error(f"데몬 실행 중 오류: {e}")
            await self.log_error("daemon_error", str(e))
        finally:
            await self.cleanup()

    async def sensor_data_loop(self):
        """센서 데이터 수집 루프"""
        logger.info(f"센서 데이터 수집 시작 (간격: {self.sensor_polling_interval}초)")

        while self.running:
            try:
                await self.collect_sensor_data()
                await asyncio.sleep(self.sensor_polling_interval)
            except Exception as e:
                logger.error(f"센서 데이터 수집 오류: {e}")
                await self.log_error("sensor_collection_error", str(e))
                await asyncio.sleep(10)  # 오류 시 10초 대기

    async def device_status_loop(self):
        """장치 상태 수집 루프"""
        logger.info(f"장치 상태 수집 시작 (간격: {self.device_status_interval}초)")

        while self.running:
            try:
                await self.collect_device_status()
                await asyncio.sleep(self.device_status_interval)
            except Exception as e:
                logger.error(f"장치 상태 수집 오류: {e}")
                await self.log_error("device_collection_error", str(e))
                await asyncio.sleep(10)  # 오류 시 10초 대기

    async def cleanup_loop(self):
        """데이터 정리 루프"""
        logger.info(f"데이터 정리 작업 시작 (간격: {self.cleanup_interval}초)")

        while self.running:
            try:
                await self.cleanup_old_data()
                await asyncio.sleep(self.cleanup_interval)
            except Exception as e:
                logger.error(f"데이터 정리 오류: {e}")
                await self.log_error("cleanup_error", str(e))
                await asyncio.sleep(300)  # 오류 시 5분 대기

    async def system_monitor_loop(self):
        """시스템 모니터링 루프"""
        start_time = time.time()

        while self.running:
            try:
                uptime = int(time.time() - start_time)
                await self.update_system_status("running", uptime=uptime)
                await asyncio.sleep(60)  # 1분마다 상태 업데이트
            except Exception as e:
                logger.error(f"시스템 모니터링 오류: {e}")
                await asyncio.sleep(30)

    async def collect_sensor_data(self):
        """센서 데이터 수집"""
        endpoint = self.api_endpoints.get('sensor_data')
        if not endpoint:
            logger.warning("센서 데이터 API 엔드포인트가 설정되지 않음")
            return

        try:
            async with self.session.get(endpoint) as response:
                if response.status == 200:
                    data = await response.json()
                    if data.get('success'):
                        await self.save_sensor_data(data.get('data', {}))
                        logger.debug("센서 데이터 수집 완료")
                    else:
                        logger.warning(f"센서 데이터 API 응답 오류: {data.get('error')}")
                else:
                    logger.warning(f"센서 데이터 API HTTP 오류: {response.status}")
        except Exception as e:
            logger.error(f"센서 데이터 수집 실패: {e}")
            raise

    async def collect_device_status(self):
        """장치 상태 수집"""
        endpoint = self.api_endpoints.get('device_status')
        if not endpoint:
            logger.warning("장치 상태 API 엔드포인트가 설정되지 않음")
            return

        try:
            async with self.session.get(endpoint) as response:
                if response.status == 200:
                    data = await response.json()
                    if data.get('success'):
                        await self.save_device_status(data.get('data', {}))
                        logger.debug("장치 상태 수집 완료")
                    else:
                        logger.warning(f"장치 상태 API 응답 오류: {data.get('error')}")
                else:
                    logger.warning(f"장치 상태 API HTTP 오류: {response.status}")
        except Exception as e:
            logger.error(f"장치 상태 수집 실패: {e}")
            raise

    async def save_sensor_data(self, data):
        """센서 데이터 데이터베이스 저장"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()

            for location, values in data.items():
                if isinstance(values, dict):
                    cursor.execute('''
                        INSERT INTO sensor_data (sensor_location, temperature, humidity, ec, ph, co2, ppfd)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ''', (
                        location,
                        values.get('temperature'),
                        values.get('humidity'),
                        values.get('ec'),
                        values.get('ph'),
                        values.get('co2'),
                        values.get('ppfd')
                    ))

            conn.commit()
            conn.close()

            # 시스템 상태 업데이트
            await self.update_system_status("running", last_sensor_update=datetime.now())

        except Exception as e:
            logger.error(f"센서 데이터 저장 실패: {e}")
            raise

    async def save_device_status(self, data):
        """장치 상태 데이터베이스 저장"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()

            for device_name, status_info in data.items():
                if isinstance(status_info, dict):
                    cursor.execute('''
                        INSERT INTO device_status (device_name, device_type, status, value, unit)
                        VALUES (?, ?, ?, ?, ?)
                    ''', (
                        device_name,
                        status_info.get('type', 'unknown'),
                        status_info.get('status', 'unknown'),
                        status_info.get('value'),
                        status_info.get('unit')
                    ))

            conn.commit()
            conn.close()

            # 시스템 상태 업데이트
            await self.update_system_status("running", last_device_update=datetime.now())

        except Exception as e:
            logger.error(f"장치 상태 저장 실패: {e}")
            raise

    async def cleanup_old_data(self):
        """오래된 데이터 정리"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()

            # 설정값 가져오기
            retention_config = self.config.get('data_retention', {})
            sensor_days = retention_config.get('sensor_data_days', 30)
            device_days = retention_config.get('device_logs_days', 7)
            error_days = retention_config.get('error_logs_days', 14)

            # 오래된 센서 데이터 삭제
            sensor_cutoff = datetime.now() - timedelta(days=sensor_days)
            cursor.execute('DELETE FROM sensor_data WHERE recorded_at < ?', (sensor_cutoff,))

            # 오래된 장치 로그 삭제
            device_cutoff = datetime.now() - timedelta(days=device_days)
            cursor.execute('DELETE FROM device_status WHERE recorded_at < ?', (device_cutoff,))

            # 오래된 에러 로그 삭제
            error_cutoff = datetime.now() - timedelta(days=error_days)
            cursor.execute('DELETE FROM error_logs WHERE recorded_at < ?', (error_cutoff,))

            conn.commit()

            # 총 레코드 수 계산
            cursor.execute('SELECT COUNT(*) FROM sensor_data')
            total_records = cursor.fetchone()[0]

            conn.close()

            logger.info(f"데이터 정리 완료 - 총 레코드: {total_records}")

            # 시스템 상태 업데이트
            await self.update_system_status("running", total_records=total_records)

        except Exception as e:
            logger.error(f"데이터 정리 실패: {e}")
            raise

    async def log_error(self, error_type, error_message, stack_trace=None):
        """에러 로그 저장"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()

            cursor.execute('''
                INSERT INTO error_logs (error_type, error_message, stack_trace)
                VALUES (?, ?, ?)
            ''', (error_type, error_message, stack_trace))

            conn.commit()
            conn.close()

        except Exception as e:
            logger.error(f"에러 로그 저장 실패: {e}")

    async def update_system_status(self, status, last_sensor_update=None, last_device_update=None,
                                  total_records=None, uptime=None):
        """시스템 상태 업데이트"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()

            # 기존 상태 조회
            cursor.execute('SELECT * FROM system_status ORDER BY id DESC LIMIT 1')
            current = cursor.fetchone()

            # 값 설정
            daemon_status = status
            sensor_update = last_sensor_update.isoformat() if last_sensor_update else (current[2] if current else None)
            device_update = last_device_update.isoformat() if last_device_update else (current[3] if current else None)
            records = total_records if total_records is not None else (current[4] if current else 0)
            uptime_sec = uptime if uptime is not None else (current[5] if current else 0)

            # 새로운 상태 삽입
            cursor.execute('''
                INSERT INTO system_status (daemon_status, last_sensor_update, last_device_update,
                                         total_records, uptime_seconds)
                VALUES (?, ?, ?, ?, ?)
            ''', (daemon_status, sensor_update, device_update, records, uptime_sec))

            # 오래된 시스템 상태 삭제 (최근 100개만 유지)
            cursor.execute('''
                DELETE FROM system_status
                WHERE id NOT IN (
                    SELECT id FROM system_status ORDER BY id DESC LIMIT 100
                )
            ''')

            conn.commit()
            conn.close()

        except Exception as e:
            logger.error(f"시스템 상태 업데이트 실패: {e}")

    async def cleanup(self):
        """정리 작업"""
        logger.info("데몬 종료 중...")

        if self.session:
            await self.session.close()

        await self.update_system_status("stopped")
        logger.info("데몬 종료 완료")

    def signal_handler(self, signum, frame):
        """시그널 핸들러"""
        logger.info(f"시그널 {signum} 수신. 데몬을 종료합니다.")
        self.running = False

# 메인 실행 부분
async def main():
    daemon = SmartFarmDaemon()

    # 시그널 핸들러 등록
    signal.signal(signal.SIGTERM, daemon.signal_handler)
    signal.signal(signal.SIGINT, daemon.signal_handler)

    try:
        await daemon.start()
    except KeyboardInterrupt:
        logger.info("키보드 인터럽트로 데몬 종료")
    except Exception as e:
        logger.error(f"데몬 실행 중 치명적 오류: {e}")
    finally:
        await daemon.cleanup()

if __name__ == "__main__":
    asyncio.run(main())