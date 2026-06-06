import { chromium } from 'playwright';
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
page.setDefaultTimeout(20000);

await page.goto('http://www.tansaeng.com/smartfarm-ui/');
await page.waitForLoadState('networkidle');
await page.screenshot({ path: '/tmp/hp_01_initial.png' });
console.log('1. 페이지 로드 완료');

const tabs = await page.locator('button, a, [role="tab"]').allTextContents();
console.log('탭 목록:', tabs.filter(t => t.trim()).slice(0, 20));
await browser.close();
