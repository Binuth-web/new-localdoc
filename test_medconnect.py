"""
MedConnect - Full System Selenium Test Suite
=============================================
Covers all major user flows:
  TC-01  Admin Login  (valid)
  TC-02  Admin Login  (invalid credentials)
  TC-03  Admin Dashboard loads & sidebar works
  TC-04  Admin Medical Centers page loads
  TC-05  Admin Users & Staff page loads
  TC-06  Admin Pending Staff Approvals visible
  TC-07  Staff Registration form loads & validation
  TC-08  Patient Login  (invalid – wrong NIC)
  TC-09  Patient Registration form visible on toggle
  TC-10  Staff Login  (invalid credentials)
  TC-11  Medical Centers API returns data
  TC-12  Index / Landing page loads

Requirements:
  pip install selenium webdriver-manager

Run:
  py test_medconnect.py
"""

import time
import unittest

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from webdriver_manager.chrome import ChromeDriverManager

# ─── CONFIG ────────────────────────────────────────────────────────────────────
BASE_URL        = "http://localhost:8000"
ADMIN_EMAIL     = "admin@medconnect"   # ← update if different
ADMIN_PASSWORD  = "admin123"           # ← update if different
WAIT_TIMEOUT    = 10                   # seconds
HEADLESS        = False                # set True to run without browser window
# ───────────────────────────────────────────────────────────────────────────────


def get_driver():
    options = Options()
    if HEADLESS:
        options.add_argument("--headless=new")
    options.add_argument("--window-size=1400,900")
    options.add_argument("--no-sandbox")
    options.add_argument("--disable-dev-shm-usage")
    options.add_experimental_option("excludeSwitches", ["enable-logging"])
    service = Service(ChromeDriverManager().install())
    return webdriver.Chrome(service=service, options=options)


class MedConnectTests(unittest.TestCase):
    """Complete Selenium test suite for MedConnect system."""

    @classmethod
    def setUpClass(cls):
        cls.driver = get_driver()
        cls.wait   = WebDriverWait(cls.driver, WAIT_TIMEOUT)
        cls.driver.implicitly_wait(5)

    @classmethod
    def tearDownClass(cls):
        cls.driver.quit()

    def setUp(self):
        """Clear cookies before each test so sessions don't leak."""
        self.driver.delete_all_cookies()

    # ─────────────────────────────────────────────────────────────────
    # HELPERS
    # ─────────────────────────────────────────────────────────────────

    def go(self, path=""):
        self.driver.get(BASE_URL + "/" + path.lstrip("/"))
        time.sleep(0.5)

    def get_alert_text(self, alert_id):
        try:
            el = self.driver.find_element(By.ID, alert_id)
            return el.text.strip()
        except Exception:
            return ""

    def admin_login(self):
        """Helper: perform a successful admin login."""
        self.go("admin_login.html")
        self.driver.find_element(By.ID, "email").send_keys(ADMIN_EMAIL)
        self.driver.find_element(By.ID, "password").send_keys(ADMIN_PASSWORD)
        self.driver.find_element(By.ID, "admin-submit-btn").click()
        # wait for redirect to dashboard
        self.wait.until(EC.url_contains("dashboard_admin"))

    # ─────────────────────────────────────────────────────────────────
    # TC-01  Admin Login – valid credentials
    # ─────────────────────────────────────────────────────────────────
    def test_01_admin_login_valid(self):
        """Admin should be redirected to dashboard after correct login."""
        print("\n[TC-01] Admin Login – valid credentials")
        self.go("admin_login.html")

        self.wait.until(EC.presence_of_element_located((By.ID, "email")))
        self.driver.find_element(By.ID, "email").clear()
        self.driver.find_element(By.ID, "email").send_keys(ADMIN_EMAIL)
        self.driver.find_element(By.ID, "password").clear()
        self.driver.find_element(By.ID, "password").send_keys(ADMIN_PASSWORD)
        self.driver.find_element(By.ID, "admin-submit-btn").click()

        self.wait.until(EC.url_contains("dashboard_admin"))
        self.assertIn("dashboard_admin", self.driver.current_url,
                      "Admin was NOT redirected to the dashboard!")
        print("    ✅  PASS – redirected to", self.driver.current_url)

    # ─────────────────────────────────────────────────────────────────
    # TC-02  Admin Login – invalid credentials
    # ─────────────────────────────────────────────────────────────────
    def test_02_admin_login_invalid(self):
        """Wrong password should show an error message."""
        print("\n[TC-02] Admin Login – invalid credentials")
        self.go("admin_login.html")

        self.wait.until(EC.presence_of_element_located((By.ID, "email")))
        self.driver.find_element(By.ID, "email").send_keys("wrong@admin.com")
        self.driver.find_element(By.ID, "password").send_keys("wrongpassword")
        self.driver.find_element(By.ID, "admin-submit-btn").click()

        time.sleep(2)
        alert = self.get_alert_text("admin-alert-box")
        self.assertTrue(len(alert) > 0,
                        "Expected an error message but got nothing!")
        self.assertNotIn("dashboard_admin", self.driver.current_url,
                         "Should NOT have redirected to dashboard with wrong credentials!")
        print("    ✅  PASS – error shown:", alert)

    # ─────────────────────────────────────────────────────────────────
    # TC-03  Admin Dashboard – page loads & sidebar navigation
    # ─────────────────────────────────────────────────────────────────
    def test_03_admin_dashboard_loads(self):
        """Dashboard should load and sidebar links should be present."""
        print("\n[TC-03] Admin Dashboard – page loads")
        self.admin_login()

        title = self.driver.title
        self.assertIn("Admin", title, f"Dashboard page title unexpected: '{title}'")

        # Check sidebar exists
        sidebar = self.driver.find_element(By.CLASS_NAME, "sidebar")
        self.assertIsNotNone(sidebar, "Sidebar not found on dashboard!")
        print("    ✅  PASS – Dashboard loaded, title:", title)

    # ─────────────────────────────────────────────────────────────────
    # TC-04  Admin Dashboard – Medical Centers section
    # ─────────────────────────────────────────────────────────────────
    def test_04_admin_centers_section(self):
        """Medical Centers section should load data."""
        print("\n[TC-04] Admin Dashboard – Medical Centers section")
        self.admin_login()

        # Click on Medical Centers nav item
        try:
            centers_link = self.driver.find_element(
                By.XPATH, "//a[contains(@onclick, 'centers') or contains(text(), 'Center')]"
            )
            centers_link.click()
        except Exception:
            # Try sidebar nav links
            nav_links = self.driver.find_elements(By.CSS_SELECTOR, ".sidebar a, .nav-item")
            for link in nav_links:
                if "center" in link.text.lower():
                    link.click()
                    break

        time.sleep(2)
        # Verify center cards / table are visible
        cards = self.driver.find_elements(By.CLASS_NAME, "card")
        self.assertGreater(len(cards), 0, "No cards found in Medical Centers section!")
        print(f"    ✅  PASS – Found {len(cards)} card(s) in Centers section")

    # ─────────────────────────────────────────────────────────────────
    # TC-05  Admin Dashboard – Users & Staff section
    # ─────────────────────────────────────────────────────────────────
    def test_05_admin_users_section(self):
        """Users & Staff section should have Patients and Staff tables."""
        print("\n[TC-05] Admin Dashboard – Users & Staff section")
        self.admin_login()

        try:
            users_link = self.driver.find_element(
                By.XPATH, "//a[contains(text(),'User') or contains(@onclick,'users')]"
            )
            users_link.click()
        except Exception:
            nav_links = self.driver.find_elements(By.CSS_SELECTOR, ".sidebar a, .nav-item")
            for link in nav_links:
                if "user" in link.text.lower():
                    link.click()
                    break

        time.sleep(2)
        # Patients and Staff tables should exist
        tables = self.driver.find_elements(By.TAG_NAME, "table")
        self.assertGreaterEqual(len(tables), 2,
                                f"Expected at least 2 tables (Patients + Staff), got {len(tables)}")
        print(f"    ✅  PASS – Found {len(tables)} table(s) in Users section")

    # ─────────────────────────────────────────────────────────────────
    # TC-06  Admin Dashboard – Pending Staff section visible
    # ─────────────────────────────────────────────────────────────────
    def test_06_admin_pending_staff_section(self):
        """Pending Staff Approvals section should be present in the DOM."""
        print("\n[TC-06] Admin Dashboard – Pending Staff section in DOM")
        self.admin_login()

        pending_card = self.driver.find_element(By.ID, "pendingStaffCard")
        self.assertIsNotNone(pending_card, "pendingStaffCard element not found!")
        print("    ✅  PASS – pendingStaffCard element is in the DOM")

    # ─────────────────────────────────────────────────────────────────
    # TC-07  Staff Registration – form loads & required fields
    # ─────────────────────────────────────────────────────────────────
    def test_07_staff_registration_form(self):
        """Staff registration form should load and require fields."""
        print("\n[TC-07] Staff Registration – form loads")
        self.go("register_staff.html")

        self.wait.until(EC.presence_of_element_located((By.ID, "staffRegisterForm")))
        form = self.driver.find_element(By.ID, "staffRegisterForm")
        self.assertIsNotNone(form, "Staff registration form not found!")

        # Try submitting empty form and check it doesn't proceed
        self.driver.find_element(By.CSS_SELECTOR, "#staffRegisterForm button[type='submit']").click()
        time.sleep(1)
        # Should still be on register page (browser validation prevents submit)
        self.assertIn("register_staff", self.driver.current_url)
        print("    ✅  PASS – Form loads and validation prevents empty submission")

    # ─────────────────────────────────────────────────────────────────
    # TC-08  Patient Login – invalid credentials
    # ─────────────────────────────────────────────────────────────────
    def test_08_patient_login_invalid(self):
        """Patient login with wrong NIC should show an error."""
        print("\n[TC-08] Patient Login – invalid credentials")
        self.go("login.html")

        self.wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "input[name='first_name']")))
        self.driver.find_element(By.CSS_SELECTOR, "input[name='first_name']").send_keys("FakeUser")
        self.driver.find_element(By.CSS_SELECTOR, "input[name='id_number']").send_keys("000000000000")
        self.driver.find_element(By.ID, "patient-submit-btn").click()

        time.sleep(2)
        alert = self.get_alert_text("patient-alert-box")
        self.assertTrue(len(alert) > 0, "Expected error message for invalid patient login!")
        print("    ✅  PASS – Error message shown:", alert)

    # ─────────────────────────────────────────────────────────────────
    # TC-09  Patient Registration – form toggle works
    # ─────────────────────────────────────────────────────────────────
    def test_09_patient_register_toggle(self):
        """'Register here' link should show the registration form."""
        print("\n[TC-09] Patient – registration form toggle")
        self.go("login.html")

        self.wait.until(EC.presence_of_element_located((By.ID, "patientLoginForm")))

        # Click Register link
        register_link = self.driver.find_element(
            By.XPATH, "//a[contains(text(),'Register here')]"
        )
        register_link.click()
        time.sleep(0.5)

        reg_form = self.driver.find_element(By.ID, "registerForm")
        is_visible = reg_form.is_displayed()
        self.assertTrue(is_visible, "Registration form should be visible after clicking 'Register here'!")
        print("    ✅  PASS – Registration form is now visible")

    # ─────────────────────────────────────────────────────────────────
    # TC-10  Staff Login – invalid credentials
    # ─────────────────────────────────────────────────────────────────
    def test_10_staff_login_invalid(self):
        """Staff login with wrong password should show an error."""
        print("\n[TC-10] Staff Login – invalid credentials")
        self.go("staff_login.html")

        self.wait.until(EC.presence_of_element_located((By.ID, "username")))
        self.driver.find_element(By.ID, "username").send_keys("fakestaff@clinic.com")
        self.driver.find_element(By.ID, "password").send_keys("wrongpassword")
        self.driver.find_element(By.ID, "staff-submit-btn").click()

        time.sleep(2)
        alert = self.get_alert_text("staff-alert-box")
        self.assertTrue(len(alert) > 0, "Expected error message for invalid staff login!")
        print("    ✅  PASS – Error message shown:", alert)

    # ─────────────────────────────────────────────────────────────────
    # TC-11  Medical Centers API – returns data
    # ─────────────────────────────────────────────────────────────────
    def test_11_get_centers_api(self):
        """The get_centers API should return a JSON success response."""
        print("\n[TC-11] Medical Centers API – returns data")
        import urllib.request, json
        url = BASE_URL + "/api/get_centers.php"
        with urllib.request.urlopen(url, timeout=10) as resp:
            data = json.loads(resp.read().decode())

        self.assertEqual(data.get("status"), "success",
                         f"API status not 'success': {data}")
        centers = data.get("data", [])
        self.assertIsInstance(centers, list, "'data' should be a list")
        print(f"    ✅  PASS – API returned {len(centers)} active center(s)")

    # ─────────────────────────────────────────────────────────────────
    # TC-12  Landing / Index page loads
    # ─────────────────────────────────────────────────────────────────
    def test_12_index_page_loads(self):
        """The main index page should load successfully."""
        print("\n[TC-12] Index/Landing page loads")
        self.go("index.html")

        self.wait.until(EC.presence_of_element_located((By.TAG_NAME, "body")))
        title = self.driver.title
        self.assertTrue(len(title) > 0, "Page title is empty!")
        body_text = self.driver.find_element(By.TAG_NAME, "body").text
        self.assertTrue(len(body_text) > 10, "Page body appears to be empty!")
        print(f"    ✅  PASS – Page loaded, title: '{title}'")


# ─── ENTRY POINT ───────────────────────────────────────────────────────────────
if __name__ == "__main__":
    print("=" * 60)
    print("  MedConnect – Full Selenium Test Suite")
    print(f"  Server : {BASE_URL}")
    print("=" * 60)
    unittest.main(verbosity=0, failfast=False)
