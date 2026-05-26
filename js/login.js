// Set initial role based on URL param if present
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const roleParam = urlParams.get('role');
    if (roleParam && ['patient', 'medical_staff', 'admin'].includes(roleParam)) {
        switchTab(roleParam);
    }
});

function switchTab(role) {
    // Update hidden input
    document.getElementById('role').value = role;
    
    // Update active tab styling
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-role') === role) {
            btn.classList.add('active');
        }
    });

    // Update button text
    const btnText = role === 'medical_staff' ? 'Medical Staff' : role.charAt(0).toUpperCase() + role.slice(1);
    document.getElementById('submit-btn').innerText = `Log in as ${btnText}`;

    // Show/hide register link (Only patients can register publicly)
    const registerLink = document.getElementById('register-link');
    if (role === 'patient') {
        registerLink.style.display = 'block';
    } else {
        registerLink.style.display = 'none';
    }
    
    // Clear alerts
    document.getElementById('alert-box').style.display = 'none';
}

function handleLogin(event) {
    event.preventDefault();
    const form = document.getElementById('loginForm');
    const formData = new FormData(form);
    const alertBox = document.getElementById('alert-box');
    const submitBtn = document.getElementById('submit-btn');

    // Basic UI loading state
    const originalBtnText = submitBtn.innerText;
    submitBtn.innerText = 'Logging in...';
    submitBtn.disabled = true;

    fetch('api/login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alertBox.className = 'alert success';
            alertBox.innerText = 'Login successful! Redirecting...';
            alertBox.style.display = 'block';
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1000);
        } else {
            alertBox.className = 'alert error';
            alertBox.innerText = data.message || 'Login failed.';
            alertBox.style.display = 'block';
            submitBtn.innerText = originalBtnText;
            submitBtn.disabled = false;
        }
    })
    .catch(err => {
        console.error(err);
        alertBox.className = 'alert error';
        alertBox.innerText = 'A network error occurred.';
        alertBox.style.display = 'block';
        submitBtn.innerText = originalBtnText;
        submitBtn.disabled = false;
    });
}

function toggleRegister(showRegister) {
    document.getElementById('loginForm').style.display = showRegister ? 'none' : 'flex';
    document.getElementById('registerForm').style.display = showRegister ? 'flex' : 'none';
    document.getElementById('alert-box').style.display = 'none';
    document.getElementById('reg-alert-box').style.display = 'none';
    
    // Hide tabs when registering
    document.querySelector('.login-tabs').style.display = showRegister ? 'none' : 'flex';
}

function handleRegister(event) {
    event.preventDefault();
    const form = document.getElementById('registerForm');
    const formData = new FormData(form);
    const alertBox = document.getElementById('reg-alert-box');
    const submitBtn = document.getElementById('reg-submit-btn');

    submitBtn.innerText = 'Registering...';
    submitBtn.disabled = true;

    fetch('api/register.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alertBox.className = 'alert success';
            alertBox.innerText = data.message;
            alertBox.style.display = 'block';
            form.reset();
            setTimeout(() => { toggleRegister(false); }, 2000);
        } else {
            alertBox.className = 'alert error';
            alertBox.innerText = data.message || 'Registration failed.';
            alertBox.style.display = 'block';
        }
        submitBtn.innerText = 'Create Account';
        submitBtn.disabled = false;
    })
    .catch(err => {
        alertBox.className = 'alert error';
        alertBox.innerText = 'A network error occurred.';
        alertBox.style.display = 'block';
        submitBtn.innerText = 'Create Account';
        submitBtn.disabled = false;
    });
}
