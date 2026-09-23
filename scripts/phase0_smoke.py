import re
import subprocess
from datetime import date, datetime, timedelta, timezone
from pathlib import Path

from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost:5173"
DEMO_PASSWORD = "OjtTracker!2026"
PHILIPPINE_TIME_ZONE = timezone(timedelta(hours=8))


def philippine_today():
    return datetime.now(PHILIPPINE_TIME_ZONE).date()


def settle(page):
    page.wait_for_load_state("networkidle")
    body = page.locator("body").inner_text().lower()
    assert "sqlstate" not in body
    assert "stack trace" not in body
    assert "whoops" not in body
    assert page.evaluate("document.documentElement.scrollWidth <= window.innerWidth")


def login(page, email, password):
    page.goto(f"{BASE_URL}/login", wait_until="domcontentloaded")
    settle(page)
    page.get_by_label("Email").fill(email)
    page.get_by_label("Password").fill(password)
    page.get_by_role("button", name="Sign in", exact=True).click()
    page.wait_for_url(re.compile(r"/student/overview$"))
    settle(page)
    page.get_by_role("heading", name="Overview").wait_for()
    page.get_by_test_id("overview-progress").wait_for()
    page.get_by_test_id("overview-context").wait_for()
    page.get_by_role("heading", name="Current Pace", exact=True).wait_for()
    page.get_by_test_id("overview-status").wait_for()
    page.get_by_test_id("overview-completion").wait_for()
    overview_text = page.locator("main").inner_text()
    assert "Edit total hours" not in overview_text
    assert "Work logs" not in overview_text
    assert re.search(r"Priority\s+\d+", overview_text) is None
    assert page.get_by_role("link", name="Overview", exact=True).is_visible()
    assert page.get_by_role("link", name="Tasks", exact=True).is_visible()
    assert page.get_by_role("link", name="Requirements", exact=True).is_visible()
    assert page.get_by_test_id("account-trigger").is_visible()
    assert page.get_by_test_id("theme-toggle").is_visible()
    assert page.get_by_role("link", name="Work Log Review", exact=True).count() == 0

    page.reload(wait_until="domcontentloaded")
    settle(page)
    page.get_by_role("heading", name="Overview").wait_for()
    assert page.get_by_role("link", name="Home", exact=True).count() == 0
    assert page.get_by_role("link", name="About", exact=True).count() == 0
    assert page.get_by_role("link", name="Sign in", exact=True).count() == 0
    assert page.get_by_role("link", name="Overview", exact=True).is_visible()
    assert page.get_by_test_id("account-trigger").is_visible()


def logout(page):
    page.get_by_test_id("account-trigger").click()
    page.get_by_test_id("account-sign-out").click()
    page.get_by_role("dialog").get_by_role("button", name="Sign out", exact=True).click()
    page.wait_for_url(re.compile(r"/login$"))
    settle(page)


def completed_hours(page):
    text = page.get_by_text(re.compile(r"\d+(?:\.\d+)? hours completed")).first.inner_text()
    return float(re.search(r"\d+(?:\.\d+)?", text).group())


def required_hours(page):
    progress = page.locator("section").filter(has_text="Rendered hours").first.inner_text()
    return int(float(re.search(r"/\s*(\d+(?:\.\d+)?) hours", progress).group(1)))


def unused_work_date(page):
    existing = " ".join(page.locator("article").all_inner_texts())
    candidate = philippine_today()
    for _ in range(30):
        value = candidate.isoformat()
        display_value = candidate.strftime("%b %d, %Y").replace(" 0", " ")
        if candidate.weekday() < 5 and value not in existing and display_value not in existing:
            return value
        candidate -= timedelta(days=1)
    raise AssertionError("no unused smoke-test work date found")


def display_date(value):
    return date.fromisoformat(value).strftime("%b %d, %Y").replace(" 0", " ")


def choose_date(page, field_id, value):
    page.locator(f"#{field_id}").click()
    calendar = page.get_by_test_id(f"{field_id}-calendar")
    calendar.wait_for()
    target = date.fromisoformat(value)

    for _ in range(120):
        day = calendar.get_by_test_id(f"calendar-day-{value}")
        if day.count() and day.is_enabled():
            day.click()
            return

        current = datetime.strptime(calendar.locator("[aria-live='polite']").inner_text(), "%B %Y").date().replace(day=1)
        button_test_id = f"{field_id}-previous" if target < current else f"{field_id}-next"
        calendar.get_by_test_id(button_test_id).click()

    raise AssertionError(f"unable to select {value} in {field_id}")


def wait_for_action_alert(page, title):
    alert = page.get_by_test_id("action-alert")
    try:
        alert.wait_for(timeout=5000)
    except Exception:
        print("action alert did not appear; body was:")
        print(page.locator("body").inner_text())
        print("form validity:")
        print(page.locator("form:visible").evaluate("""form => ({
            id: form.id,
            valid: form.checkValidity(),
            fields: Array.from(form.elements).map(field => ({
                id: field.id,
                value: field.value,
                valid: field.checkValidity(),
                validationMessage: field.validationMessage,
            })),
        })"""))
        raise
    assert alert.get_by_role("heading", name=title, exact=True).is_visible()


def check_auth_brand(page):
    brand = page.get_by_test_id("auth-brand")
    label = page.get_by_test_id("auth-brand-label")
    assert brand.get_attribute("href") == "/"
    assert brand.bounding_box()["width"] < page.locator("section").first.bounding_box()["width"]
    brand.hover()
    page.wait_for_timeout(220)
    transform = page.evaluate("element => getComputedStyle(element, '::after').transform", label.element_handle())
    assert transform != "none" and not transform.startswith("matrix(0")


def register_student(page):
    today = philippine_today()
    start_date = (today - timedelta(days=7)).isoformat()
    end_date = (today + timedelta(days=30)).isoformat()
    email = f"browser-registration-{datetime.now().strftime('%Y%m%d%H%M%S%f')}@example.com"
    password = "StrongPassword1!"

    page.set_viewport_size({"width": 390, "height": 844})
    page.goto(f"{BASE_URL}/register", wait_until="domcontentloaded")
    settle(page)
    page.get_by_test_id("registration-step-1").wait_for()

    page.get_by_label("Name").fill("Existing Account Attempt")
    page.get_by_label("Email").fill("student@example.com")
    page.get_by_label("Password", exact=True).fill(password)
    page.get_by_label("Confirm password").fill(password)
    page.get_by_role("button", name="Continue", exact=True).click()
    page.get_by_test_id("field-error-email").wait_for()
    assert page.get_by_test_id("registration-step-2").count() == 0

    page.get_by_label("Name").fill("Browser Registration Student")
    page.get_by_label("Email").fill(email)
    page.get_by_label("Password", exact=True).fill(password)
    page.get_by_label("Confirm password").fill(password)
    page.get_by_role("button", name="Continue", exact=True).click()
    page.get_by_test_id("registration-step-2").wait_for()

    page.get_by_role("button", name="Back", exact=True).click()
    assert page.get_by_label("Email").input_value() == email
    page.get_by_role("button", name="Continue", exact=True).click()

    page.get_by_label("Required OJT hours").fill("500")
    invalid_start_date = (today + timedelta(days=31)).isoformat()
    choose_date(page, "register-end-date", end_date)
    choose_date(page, "register-start-date", invalid_start_date)
    page.get_by_label("Expected hours per day").fill("8")
    assert page.get_by_label("Mon").is_checked()
    assert page.get_by_label("Fri").is_checked()
    page.get_by_role("button", name="Start tracking", exact=True).click()
    page.get_by_test_id("end-date-error").wait_for()
    assert page.get_by_test_id("end-date-error").is_visible()
    assert page.url.endswith("/register")

    choose_date(page, "register-start-date", start_date)
    page.get_by_role("button", name="Start tracking", exact=True).click()
    page.wait_for_url(re.compile(r"/student/overview$"))
    settle(page)
    page.get_by_role("heading", name="Overview").wait_for()
    page.get_by_test_id("overview-progress").wait_for()
    page.goto(f"{BASE_URL}/student/work-hours", wait_until="domcontentloaded")
    settle(page)
    try:
        page.get_by_role("heading", name="Rendered hours").wait_for()
    except Exception:
        print(f"student page failed to render at {page.url}:")
        print(page.locator("body").inner_text())
        raise
    assert page.get_by_text(re.compile(r"/\s*500 hours")).is_visible()
    period = page.get_by_test_id("internship-period")
    assert display_date(start_date) in period.inner_text()
    assert display_date(end_date) in period.inner_text()
    assert page.evaluate("document.documentElement.scrollWidth <= window.innerWidth")
    return email, password, start_date


def cleanup_smoke_data():
    backend = Path(__file__).resolve().parents[1] / "backend"
    subprocess.run(
        ["php", "artisan", "db:seed", "--class=DatabaseSeeder", "--force"],
        cwd=backend,
        check=True,
    )


with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    browser_context = browser.new_context()
    page = browser_context.new_page()

    try:
        page.goto(BASE_URL, wait_until="domcontentloaded")
        settle(page)
        assert page.get_by_role("heading", name="Track your OJT progress with clarity.").is_visible()
        assert page.get_by_role("link", name="Progress", exact=True).count() == 0
        assert page.get_by_role("link", name="Features", exact=True).count() == 0
        assert "sticky" in page.locator("header").get_attribute("class")
        for width in (1024, 1366, 1440, 1920):
            page.set_viewport_size({"width": width, "height": 900})
            page.goto(BASE_URL, wait_until="domcontentloaded")
            settle(page)
            assert page.get_by_role("navigation", name="Main navigation").is_visible()
        for width in (375, 390, 430):
            page.set_viewport_size({"width": width, "height": 844})
            for public_path in ("/", "/about"):
                page.goto(f"{BASE_URL}{public_path}", wait_until="domcontentloaded")
                settle(page)
                assert page.get_by_role("navigation", name="Main navigation").is_visible()
                page.get_by_test_id("mobile-menu-trigger").click()
                mobile_drawer = page.get_by_test_id("mobile-navigation-drawer")
                mobile_drawer.wait_for()
                assert mobile_drawer.get_by_role("link", name="Home", exact=True).is_visible()
                assert mobile_drawer.get_by_role("link", name="About", exact=True).is_visible()
                assert mobile_drawer.get_by_role("link", name="Sign in", exact=True).is_visible()
                assert mobile_drawer.get_by_role("link", name="Create student account", exact=True).is_visible()
                page.keyboard.press("Escape")
                page.wait_for_timeout(250)
                assert not mobile_drawer.is_visible()
        page.set_viewport_size({"width": 1024, "height": 900})
        page.goto(f"{BASE_URL}/login", wait_until="domcontentloaded")
        settle(page)
        check_auth_brand(page)
        page.get_by_test_id("auth-brand").click()
        page.wait_for_url(re.compile(r"/$"))
        settle(page)
        page.goto(f"{BASE_URL}/register", wait_until="domcontentloaded")
        settle(page)
        check_auth_brand(page)
        page.get_by_test_id("auth-brand").click()
        page.wait_for_url(re.compile(r"/$"))
        settle(page)
        public_theme_toggle = page.get_by_test_id("theme-toggle")
        assert public_theme_toggle.get_attribute("aria-label") == "Switch to dark mode"
        public_theme_toggle.click()
        assert page.locator("html").get_attribute("data-theme") == "dark"
        page.reload(wait_until="domcontentloaded")
        settle(page)
        assert page.locator("html").get_attribute("data-theme") == "dark"
        page.goto(f"{BASE_URL}/login", wait_until="domcontentloaded")
        settle(page)
        assert page.locator("html").get_attribute("data-theme") == "dark"
        page.get_by_role("heading", name="Welcome back").wait_for()
        page.goto(f"{BASE_URL}/register", wait_until="domcontentloaded")
        settle(page)
        assert page.locator("html").get_attribute("data-theme") == "dark"
        page.get_by_role("heading", name="Create your account").wait_for()
        page.goto(BASE_URL, wait_until="domcontentloaded")
        settle(page)
        page.get_by_test_id("theme-toggle").click()
        assert page.locator("html").get_attribute("data-theme") == "light"
        page.goto(f"{BASE_URL}/#progress-preview", wait_until="domcontentloaded")
        settle(page)
        page.get_by_test_id("home-progress-preview").scroll_into_view_if_needed()
        page.wait_for_function(
            "selector => document.querySelector(selector)?.getAttribute('aria-valuenow') === '68'",
            arg="[role='progressbar']",
        )
        page.goto(f"{BASE_URL}/#features", wait_until="domcontentloaded")
        settle(page)
        page.get_by_test_id("home-feature-grid").scroll_into_view_if_needed()
        page.wait_for_function(
            "selector => document.querySelector(selector)?.classList.contains('motion-revealed')",
            arg="[data-testid='home-feature-grid']",
        )

        page.get_by_label("Main navigation").get_by_role("link", name="About", exact=True).click()
        page.wait_for_url(re.compile(r"/about$"))
        settle(page)
        page.get_by_role("heading", name="Built for student interns who want a clearer finish line.").wait_for()
        assert page.get_by_role("heading", name="Built for student interns who want a clearer finish line.").is_visible()
        page.get_by_test_id("about-benefits").scroll_into_view_if_needed()
        page.wait_for_function(
            "selector => document.querySelector(selector)?.classList.contains('motion-revealed')",
            arg="[data-testid='about-benefits']",
        )

        page.emulate_media(reduced_motion="reduce")
        page.goto(BASE_URL, wait_until="domcontentloaded")
        settle(page)
        page.get_by_test_id("home-progress-preview").scroll_into_view_if_needed()
        assert page.get_by_role("progressbar").get_attribute("aria-valuenow") == "68"
        assert page.get_by_test_id("preview-rendered-hours").inner_text() == "340"
        page.emulate_media(reduced_motion="no-preference")

        page.goto(f"{BASE_URL}/does-not-exist", wait_until="domcontentloaded")
        settle(page)
        assert page.get_by_role("heading", name="Page not found").is_visible()

        email, password, start_date = register_student(page)
        page.get_by_test_id("theme-toggle").click()
        assert page.locator("html").get_attribute("data-theme") == "dark"
        for path in ("/student/tasks", "/student/requirements", "/student/profile"):
            page.goto(f"{BASE_URL}{path}", wait_until="domcontentloaded")
            settle(page)
            assert page.locator("html").get_attribute("data-theme") == "dark"
        page.goto(f"{BASE_URL}/student/profile", wait_until="domcontentloaded")
        settle(page)
        profile_end_date = page.locator("#profile-end-date")
        assert profile_end_date.get_attribute("type") == "text"
        assert profile_end_date.input_value()
        page.locator("#profile-start-date").click()
        page.get_by_test_id("profile-start-date-calendar").wait_for()
        page.get_by_test_id("cancel-profile-start-date-picker").click()
        page.get_by_label("New password", exact=True).fill("NewStrongPassword1!")
        page.get_by_label("Confirm new password", exact=True).fill("NewStrongPassword1!")
        assert page.locator("#profile-current-password").get_attribute("required") is not None
        page.get_by_label("Current password", exact=True).fill("wrong-password")
        page.get_by_role("button", name="Save changes", exact=True).click()
        page.get_by_text("The password is incorrect.", exact=True).wait_for()

        page.goto(f"{BASE_URL}/student/tasks", wait_until="domcontentloaded")
        settle(page)
        page.get_by_test_id("add-task").click()
        registered_task_dialog = page.get_by_role("dialog")
        assert registered_task_dialog.locator("#task-due-date").get_attribute("data-max")
        registered_task_dialog.locator("#task-due-date").click()
        page.get_by_test_id("task-due-date-calendar").wait_for()
        page.get_by_test_id("cancel-task-due-date-picker").click()
        registered_task_dialog.get_by_role("button", name="Cancel", exact=True).click()

        page.goto(f"{BASE_URL}/student/requirements", wait_until="domcontentloaded")
        settle(page)
        page.get_by_test_id("add-requirement").click()
        registered_requirement_dialog = page.get_by_role("dialog")
        assert registered_requirement_dialog.locator("#requirement-due-date").get_attribute("data-max")
        registered_requirement_dialog.locator("#requirement-due-date").click()
        page.get_by_test_id("requirement-due-date-calendar").wait_for()
        page.get_by_test_id("cancel-requirement-due-date-picker").click()
        registered_requirement_dialog.get_by_role("button", name="Cancel", exact=True).click()

        logout(page)
        login(page, email, password)
        page.close()
        page = browser_context.new_page()
        page.goto(BASE_URL, wait_until="domcontentloaded")
        settle(page)
        assert page.url.endswith("/student/overview")
        page.get_by_role("heading", name="Overview").wait_for()
        for width in (375, 390, 430):
            page.set_viewport_size({"width": width, "height": 844})
            for student_path in ("/student/overview", "/student/work-hours", "/student/tasks", "/student/requirements"):
                page.goto(f"{BASE_URL}{student_path}", wait_until="domcontentloaded")
                settle(page)
                assert page.get_by_test_id("theme-toggle").is_visible()
                assert page.get_by_test_id("account-trigger").is_visible()
                assert page.get_by_test_id("mobile-bottom-nav").is_visible()
                assert page.get_by_test_id("mobile-bottom-nav-overview").is_visible()
                assert page.get_by_test_id("mobile-bottom-nav-work-hours").is_visible()
                assert page.get_by_test_id("mobile-bottom-nav-tasks").is_visible()
                assert page.get_by_test_id("mobile-bottom-nav-requirements").is_visible()

        page.set_viewport_size({"width": 375, "height": 812})
        page.goto(f"{BASE_URL}/student/overview", wait_until="domcontentloaded")
        settle(page)
        page.get_by_test_id("mobile-menu-trigger").click()
        mobile_drawer = page.get_by_test_id("mobile-navigation-drawer")
        mobile_drawer.wait_for()
        assert mobile_drawer.get_by_role("link", name="Profile & Password", exact=True).count() == 0
        assert mobile_drawer.get_by_role("button", name="Sign out", exact=True).count() == 0
        page.keyboard.press("Escape")
        page.wait_for_timeout(250)
        assert not mobile_drawer.is_visible()
        page.get_by_test_id("account-trigger").click()
        account_menu = page.get_by_test_id("account-menu")
        account_menu.wait_for()
        menu_box = account_menu.bounding_box()
        assert menu_box["x"] >= 0
        assert menu_box["x"] + menu_box["width"] <= page.viewport_size["width"]
        page.keyboard.press("Escape")
        page.wait_for_timeout(250)
        assert not account_menu.is_visible()
        page.set_viewport_size({"width": 390, "height": 844})
        current_url = page.url
        brand = page.get_by_test_id("app-brand")
        assert brand.evaluate("element => element.tagName") == "DIV"
        brand.click()
        assert page.url == current_url

        page.goto(f"{BASE_URL}/student/work-hours", wait_until="domcontentloaded")
        settle(page)
        original_required_hours = required_hours(page)
        goal = page.locator("section").filter(has_text="Rendered hours").first
        page.get_by_test_id("edit-hours-goal").click()
        goal_dialog = page.get_by_role("dialog")
        goal_dialog.locator("#goal-hours").fill(str(original_required_hours + 1))
        goal_dialog.get_by_role("button", name="Save goal", exact=True).click()
        wait_for_action_alert(page, "Saved successfully")
        assert f"/ {original_required_hours + 1} hours" in goal.inner_text()
        page.get_by_test_id("alert-close").click()

        page.get_by_test_id("edit-hours-goal").click()
        goal_dialog = page.get_by_role("dialog")
        goal_dialog.locator("#goal-hours").fill(str(original_required_hours))
        goal_dialog.get_by_role("button", name="Save goal", exact=True).click()
        wait_for_action_alert(page, "Saved successfully")
        page.get_by_test_id("alert-close").click()

        before_hours = completed_hours(page)
        work_date = unused_work_date(page)

        page.get_by_test_id("add-work-log").click()
        dialog = page.get_by_role("dialog")
        choose_date(page, "work-date", work_date)
        dialog.locator("#time-in").fill("08:00")
        dialog.locator("#time-out").fill("17:00")
        dialog.locator("#break-minutes").fill("60")
        dialog.locator("#accomplishment").fill("Completed deterministic browser smoke work.")
        dialog.get_by_role("button", name="Save work log", exact=True).click()
        wait_for_action_alert(page, "Saved successfully")
        page.get_by_test_id("alert-close").click()

        work_date_display = date.fromisoformat(work_date).strftime("%b %d, %Y").replace(" 0", " ")
        work_log = page.locator("article").filter(has_text=work_date_display)
        assert re.search(r"\b8h\b", work_log.inner_text())
        assert work_log.get_by_role("status").count() == 0
        assert work_log.get_by_role("button", name="Edit", exact=True).is_visible()
        assert work_log.get_by_role("button", name="Delete", exact=True).is_visible()
        assert work_log.get_by_role("button", name="Complete", exact=True).count() == 0
        assert completed_hours(page) > before_hours

        page.goto(f"{BASE_URL}/student/overview", wait_until="domcontentloaded")
        settle(page)
        page.get_by_test_id("overview-progress").wait_for()
        page.get_by_test_id("overview-status").wait_for()
        page.get_by_test_id("overview-completion").wait_for()
        assert page.get_by_test_id("overview-rendered-hours").is_visible()

        page.goto(f"{BASE_URL}/student/tasks", wait_until="domcontentloaded")
        settle(page)
        page.get_by_test_id("add-task").click()
        dialog = page.get_by_role("dialog")
        assert dialog.locator("#task-due-date").get_attribute("data-min") == start_date
        dialog.get_by_role("button", name="Cancel", exact=True).click()
        page.get_by_test_id("add-task").click()
        dialog = page.get_by_role("dialog")
        task_title = f"Browser smoke task {datetime.now().strftime('%Y%m%d%H%M%S%f')}"
        dialog.locator("#task-title").fill(task_title)
        dialog.locator("#task-description").fill("Created by the student-only browser smoke.")
        dialog.get_by_role("button", name="Add task", exact=True).click()
        wait_for_action_alert(page, "Saved successfully")
        page.get_by_test_id("alert-close").click()
        task = page.locator("article").filter(has_text=task_title)
        task.get_by_role("button", name="Start", exact=True).click()
        page.get_by_text("Updated successfully", exact=True).wait_for()
        page.get_by_test_id("alert-close").click()
        task = page.locator("article").filter(has_text=task_title)
        task.get_by_role("button", name="Mark completed", exact=True).click()
        page.get_by_text("Updated successfully", exact=True).wait_for()
        page.get_by_test_id("alert-close").click()

        page.goto(f"{BASE_URL}/student/requirements", wait_until="domcontentloaded")
        settle(page)
        page.get_by_test_id("add-requirement").click()
        dialog = page.get_by_role("dialog")
        assert dialog.locator("#requirement-due-date").get_attribute("data-min") == start_date
        dialog.locator("#requirement-title").fill("Browser smoke requirement")
        choose_date(page, "requirement-due-date", (philippine_today() + timedelta(days=7)).isoformat())
        dialog.locator("#requirement-description").fill("Created by the student-only browser smoke.")
        dialog.get_by_role("button", name="Add requirement", exact=True).click()
        wait_for_action_alert(page, "Saved successfully")
        page.get_by_test_id("alert-close").click()
        requirement = page.locator("article").filter(has_text="Browser smoke requirement")
        requirement.get_by_role("button", name="Mark completed", exact=True).click()
        page.get_by_text("Updated successfully", exact=True).wait_for()
        page.get_by_test_id("alert-close").click()

        logout(page)
        login(page, email, password)
        page.goto(f"{BASE_URL}/student/work-hours", wait_until="domcontentloaded")
        settle(page)
        page.get_by_text(work_date_display, exact=True).wait_for()
        page.goto(f"{BASE_URL}/student/tasks", wait_until="domcontentloaded")
        settle(page)
        page.get_by_text(task_title, exact=True).wait_for()
        page.goto(f"{BASE_URL}/student/requirements", wait_until="domcontentloaded")
        settle(page)
        page.get_by_text("Browser smoke requirement", exact=True).wait_for()
        assert page.get_by_text("Completed", exact=True).count() >= 1
        logout(page)

        login(page, "student@example.com", DEMO_PASSWORD)
        page.get_by_role("heading", name="Overview").wait_for()
        page.get_by_test_id("overview-attention").wait_for()
        logout(page)
        print("browser smoke: registration setup, overview, mobile layout, date validation, same-account relogin persistence, and canonical demo login passed")
    finally:
        browser.close()
        cleanup_smoke_data()
