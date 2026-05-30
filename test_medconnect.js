/**
 * MedConnect – Full System Selenium Test Suite (JavaScript)
 * ==========================================================
 * TC-01  Admin Login  – valid credentials
 * TC-02  Admin Login  – invalid credentials (error message shown)
 * TC-03  Admin Dashboard loads (sidebar visible)
 * TC-04  Admin Medical Centers section loads
 * TC-05  Admin Users & Staff – two tables present
 * TC-06  Admin Pending Staff section in DOM
 * TC-07  Staff Registration form loads & empty submit blocked
 * TC-08  Patient Login  – invalid credentials (error shown)
 * TC-09  Patient Registration form toggle works
 * TC-10  Staff Login   – invalid credentials (error shown)
 * TC-11  Medical Centers API returns active centers
 * TC-12  Landing / Index page loads
 *
 * Requirements (already installed):
 *   npm install selenium-webdriver chromedriver
 *
 * Run:
 *   node test_medconnect.js
 */

const { Builder, By, until } = require("selenium-webdriver");
const chrome = require("selenium-webdriver/chrome");
const http   = require("http");
require("chromedriver");

// ─── CONFIG ─────────────────────────────────────────────────────────────────
const BASE_URL       = "http://localhost:8000";
const ADMIN_EMAIL    = "admin@medconnect.com";  // ← change if different
const ADMIN_PASSWORD = "admin123";          // ← change if different
const TIMEOUT        = 10_000;             // ms
const HEADLESS       = false;              // true = no browser window
// ─────────────────────────────────────────────────────────────────────────────

// ─── RESULT TRACKING ─────────────────────────────────────────────────────────
let passed = 0, failed = 0;
const results = [];

function pass(name) {
  passed++;
  results.push({ name, status: "PASS" });
  console.log(`  ✅ PASS – ${name}`);
}

function fail(name, err) {
  failed++;
  results.push({ name, status: "FAIL", error: err.message });
  console.log(`  ❌ FAIL – ${name}`);
  console.log(`       ${err.message}`);
}
// ─────────────────────────────────────────────────────────────────────────────

async function buildDriver() {
  const opts = new chrome.Options();
  if (HEADLESS) opts.addArguments("--headless=new");
  opts.addArguments("--window-size=1400,900", "--no-sandbox", "--disable-dev-shm-usage", "--log-level=3");
  return new Builder().forBrowser("chrome").setChromeOptions(opts).build();
}

async function go(driver, path = "") {
  await driver.get(`${BASE_URL}/${path.replace(/^\//, "")}`);
  await driver.sleep(600);
}

async function adminLogin(driver) {
  await go(driver, "admin_login.html");
  await driver.wait(until.elementLocated(By.id("email")), TIMEOUT);
  await driver.findElement(By.id("email")).sendKeys(ADMIN_EMAIL);
  await driver.findElement(By.id("password")).sendKeys(ADMIN_PASSWORD);
  await driver.findElement(By.id("admin-submit-btn")).click();
  await driver.wait(until.urlContains("dashboard_admin"), TIMEOUT);
}

async function getAlertText(driver, id) {
  try {
    return await driver.findElement(By.id(id)).getText();
  } catch { return ""; }
}

// ─────────────────────────────────────────────────────────────────────────────
// TEST RUNNER
// ─────────────────────────────────────────────────────────────────────────────
async function runTests() {
  console.log("=".repeat(60));
  console.log("  MedConnect – Full Selenium Test Suite (JavaScript)");
  console.log(`  Server : ${BASE_URL}`);
  console.log("=".repeat(60));

  const driver = await buildDriver();

  try {
    // ── TC-01  Admin Login – valid ──────────────────────────────────────────
    console.log("\n[TC-01] Admin Login – valid credentials");
    try {
      await driver.manage().deleteAllCookies();
      await go(driver, "admin_login.html");
      await driver.wait(until.elementLocated(By.id("email")), TIMEOUT);
      await driver.findElement(By.id("email")).sendKeys(ADMIN_EMAIL);
      await driver.findElement(By.id("password")).sendKeys(ADMIN_PASSWORD);
      await driver.findElement(By.id("admin-submit-btn")).click();
      await driver.wait(until.urlContains("dashboard_admin"), TIMEOUT);
      const url = await driver.getCurrentUrl();
      if (!url.includes("dashboard_admin")) throw new Error("Not redirected to dashboard");
      pass("Admin Login – valid credentials");
    } catch (e) { fail("Admin Login – valid credentials", e); }

    // ── TC-02  Admin Login – invalid ────────────────────────────────────────
    console.log("\n[TC-02] Admin Login – invalid credentials");
    try {
      await driver.manage().deleteAllCookies();
      await go(driver, "admin_login.html");
      await driver.wait(until.elementLocated(By.id("email")), TIMEOUT);
      await driver.findElement(By.id("email")).sendKeys("wrong@test.com");
      await driver.findElement(By.id("password")).sendKeys("badpassword");
      await driver.findElement(By.id("admin-submit-btn")).click();
      await driver.sleep(2000);
      const alertText = await getAlertText(driver, "admin-alert-box");
      if (!alertText) throw new Error("No error message displayed");
      const url = await driver.getCurrentUrl();
      if (url.includes("dashboard_admin")) throw new Error("Should not redirect with wrong credentials");
      pass("Admin Login – invalid credentials");
    } catch (e) { fail("Admin Login – invalid credentials", e); }

    // ── TC-03  Admin Dashboard loads ────────────────────────────────────────
    console.log("\n[TC-03] Admin Dashboard loads");
    try {
      await driver.manage().deleteAllCookies();
      await adminLogin(driver);
      const title = await driver.getTitle();
      if (!title.toLowerCase().includes("admin")) throw new Error(`Unexpected title: ${title}`);
      await driver.findElement(By.className("sidebar")); // throws if not found
      pass("Admin Dashboard loads");
    } catch (e) { fail("Admin Dashboard loads", e); }

    // ── TC-04  Admin Medical Centers section ────────────────────────────────
    console.log("\n[TC-04] Admin Medical Centers section");
    try {
      await driver.manage().deleteAllCookies();
      await adminLogin(driver);
      // Click Centers nav link
      const links = await driver.findElements(By.css(".sidebar a, .nav-item"));
      for (const link of links) {
        const txt = (await link.getText()).toLowerCase();
        if (txt.includes("center")) { await link.click(); break; }
      }
      await driver.sleep(1500);
      const cards = await driver.findElements(By.className("card"));
      if (cards.length === 0) throw new Error("No cards found in Centers section");
      pass(`Admin Medical Centers section (${cards.length} card(s))`);
    } catch (e) { fail("Admin Medical Centers section", e); }

    // ── TC-05  Admin Users & Staff – two tables ──────────────────────────────
    console.log("\n[TC-05] Admin Users & Staff – Patients and Staff tables");
    try {
      await driver.manage().deleteAllCookies();
      await adminLogin(driver);
      const links = await driver.findElements(By.css(".sidebar a, .nav-item"));
      for (const link of links) {
        const txt = (await link.getText()).toLowerCase();
        if (txt.includes("user")) { await link.click(); break; }
      }
      await driver.sleep(1500);
      const tables = await driver.findElements(By.tagName("table"));
      if (tables.length < 2) throw new Error(`Expected ≥2 tables, found ${tables.length}`);
      pass(`Admin Users & Staff (${tables.length} table(s) found)`);
    } catch (e) { fail("Admin Users & Staff tables", e); }

    // ── TC-06  Admin Pending Staff section in DOM ────────────────────────────
    console.log("\n[TC-06] Admin Pending Staff section in DOM");
    try {
      await driver.manage().deleteAllCookies();
      await adminLogin(driver);
      const el = await driver.findElement(By.id("pendingStaffCard"));
      if (!el) throw new Error("pendingStaffCard not in DOM");
      pass("Admin Pending Staff section in DOM");
    } catch (e) { fail("Admin Pending Staff section in DOM", e); }

    // ── TC-07  Staff Registration form ──────────────────────────────────────
    console.log("\n[TC-07] Staff Registration form loads");
    try {
      await driver.manage().deleteAllCookies();
      await go(driver, "register_staff.html");
      await driver.wait(until.elementLocated(By.id("staffRegisterForm")), TIMEOUT);
      // Submit empty form – browser validation should block it
      await driver.findElement(By.css("#staffRegisterForm button[type='submit']")).click();
      await driver.sleep(800);
      const url = await driver.getCurrentUrl();
      if (!url.includes("register_staff")) throw new Error("Form submitted without validation!");
      pass("Staff Registration form loads & validation works");
    } catch (e) { fail("Staff Registration form", e); }

    // ── TC-08  Patient Login – invalid ──────────────────────────────────────
    console.log("\n[TC-08] Patient Login – invalid credentials");
    try {
      await driver.manage().deleteAllCookies();
      await go(driver, "login.html");
      await driver.wait(until.elementLocated(By.css("input[name='first_name']")), TIMEOUT);
      await driver.findElement(By.css("input[name='first_name']")).sendKeys("FakeUser");
      await driver.findElement(By.css("input[name='id_number']")).sendKeys("000000000000");
      await driver.findElement(By.id("patient-submit-btn")).click();
      await driver.sleep(2000);
      const alertText = await getAlertText(driver, "patient-alert-box");
      if (!alertText) throw new Error("No error message displayed for invalid patient login");
      pass(`Patient Login – invalid (msg: "${alertText}")`);
    } catch (e) { fail("Patient Login – invalid credentials", e); }

    // ── TC-09  Patient Registration toggle ──────────────────────────────────
    console.log("\n[TC-09] Patient Registration form toggle");
    try {
      await driver.manage().deleteAllCookies();
      await go(driver, "login.html");
      await driver.wait(until.elementLocated(By.id("patientLoginForm")), TIMEOUT);
      const regLink = await driver.findElement(By.xpath("//a[contains(text(),'Register here')]"));
      await regLink.click();
      await driver.sleep(500);
      const regForm = await driver.findElement(By.id("registerForm"));
      const visible = await regForm.isDisplayed();
      if (!visible) throw new Error("Registration form not visible after toggle");
      pass("Patient Registration form toggle works");
    } catch (e) { fail("Patient Registration form toggle", e); }

    // ── TC-10  Staff Login – invalid ─────────────────────────────────────────
    console.log("\n[TC-10] Staff Login – invalid credentials");
    try {
      await driver.manage().deleteAllCookies();
      await go(driver, "staff_login.html");
      await driver.wait(until.elementLocated(By.id("username")), TIMEOUT);
      await driver.findElement(By.id("username")).sendKeys("fakestaff@clinic.com");
      await driver.findElement(By.id("password")).sendKeys("wrongpassword");
      await driver.findElement(By.id("staff-submit-btn")).click();
      await driver.sleep(2000);
      const alertText = await getAlertText(driver, "staff-alert-box");
      if (!alertText) throw new Error("No error message for invalid staff login");
      pass(`Staff Login – invalid (msg: "${alertText}")`);
    } catch (e) { fail("Staff Login – invalid credentials", e); }

    // ── TC-11  Medical Centers API ───────────────────────────────────────────
    console.log("\n[TC-11] Medical Centers API returns data");
    try {
      await new Promise((resolve, reject) => {
        http.get(`${BASE_URL}/api/get_centers.php`, (res) => {
          let data = "";
          res.on("data", chunk => data += chunk);
          res.on("end", () => {
            try {
              const json = JSON.parse(data);
              if (json.status !== "success") throw new Error(`status = ${json.status}`);
              if (!Array.isArray(json.data))  throw new Error("data is not an array");
              console.log(`       API returned ${json.data.length} active center(s)`);
              resolve();
            } catch (err) { reject(err); }
          });
        }).on("error", reject);
      });
      pass("Medical Centers API returns active centers");
    } catch (e) { fail("Medical Centers API", e); }

    // ── TC-12  Landing / Index page ──────────────────────────────────────────
    console.log("\n[TC-12] Landing / Index page loads");
    try {
      await driver.manage().deleteAllCookies();
      await go(driver, "index.html");
      await driver.wait(until.elementLocated(By.tagName("body")), TIMEOUT);
      const title = await driver.getTitle();
      if (!title) throw new Error("Page title is empty");
      pass(`Index page loads (title: "${title}")`);
    } catch (e) { fail("Index page loads", e); }

  } finally {
    await driver.quit();
  }

  // ── SUMMARY ────────────────────────────────────────────────────────────────
  console.log("\n" + "=".repeat(60));
  console.log("  TEST RESULTS SUMMARY");
  console.log("=".repeat(60));
  results.forEach((r, i) => {
    const icon = r.status === "PASS" ? "✅" : "❌";
    console.log(`  ${String(i + 1).padStart(2, "0")}. ${icon} ${r.status}  –  ${r.name}`);
    if (r.error) console.log(`        Error: ${r.error}`);
  });
  console.log("─".repeat(60));
  console.log(`  Total: ${results.length}  |  Passed: ${passed}  |  Failed: ${failed}`);
  console.log("=".repeat(60));

  if (failed > 0) process.exit(1);
}

runTests().catch(err => {
  console.error("\nFATAL:", err.message);
  process.exit(1);
});
