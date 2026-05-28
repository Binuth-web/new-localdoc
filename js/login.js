// Set initial role based on URL param if present
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const roleParam = urlParams.get('role');
    if (roleParam && ['patient', 'medical_staff', 'admin'].includes(roleParam)) {
        switchTab(roleParam);
    } else {
        switchTab('patient'); // Default
    }

    const redirect = urlParams.get('redirect');
    if (redirect) {
        const note = document.createElement('p');
        note.style.cssText = 'text-align:center;color:var(--text-muted);font-size:0.9rem;margin:0 2rem 1rem;';
        note.textContent = 'Please log in to continue.';
        const box = document.querySelector('.login-box');
        if (box) box.insertBefore(note, box.querySelector('.login-tabs'));
    }
});

function switchTab(role) {
    // Update active tab styling
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-role') === role) {
            btn.classList.add('active');
        }
    });

    const staffForm = document.getElementById('staffLoginForm');
    const patientForm = document.getElementById('patientLoginForm');
    const registerForm = document.getElementById('registerForm');
    
    // Clear alerts
    document.querySelectorAll('.alert').forEach(a => a.style.display = 'none');

    registerForm.style.display = 'none';

    if (role === 'patient') {
        staffForm.style.display = 'none';
        patientForm.style.display = 'flex';
    } else {
        patientForm.style.display = 'none';
        staffForm.style.display = 'flex';
        document.getElementById('staff_role').value = role;
        
        const btnText = role === 'medical_staff' ? 'Medical Staff' : 'Admin';
        document.getElementById('staff-submit-btn').innerText = `Log in as ${btnText}`;
    }
}

function toggleRegister(showRegister) {
    document.getElementById('patientLoginForm').style.display = showRegister ? 'none' : 'flex';
    document.getElementById('registerForm').style.display = showRegister ? 'flex' : 'none';
    document.getElementById('staffLoginForm').style.display = 'none';
    document.querySelectorAll('.alert').forEach(a => a.style.display = 'none');
    
    // Hide tabs when registering
    document.querySelector('.login-tabs').style.display = showRegister ? 'none' : 'flex';
}

function handleLogin(event) {
    event.preventDefault();
    const form = document.getElementById('staffLoginForm');
    const formData = new FormData(form);
    const alertBox = document.getElementById('staff-alert-box');
    const submitBtn = document.getElementById('staff-submit-btn');

    const originalBtnText = submitBtn.innerText;
    submitBtn.innerText = 'Logging in...';
    submitBtn.disabled = true;

    fetch('api/login.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            alertBox.className = 'alert success';
            alertBox.innerText = 'Login successful! Redirecting...';
            alertBox.style.display = 'block';
            setTimeout(() => { window.location.href = data.redirect; }, 1000);
        } else {
            alertBox.className = 'alert error';
            alertBox.innerText = data.message || 'Login failed.';
            alertBox.style.display = 'block';
            submitBtn.innerText = originalBtnText;
            submitBtn.disabled = false;
        }
    })
    .catch(err => {
        alertBox.className = 'alert error';
        alertBox.innerText = 'A network error occurred.';
        alertBox.style.display = 'block';
        submitBtn.innerText = originalBtnText;
        submitBtn.disabled = false;
    });
}

function handlePatientLogin(event) {
    event.preventDefault();
    const form = document.getElementById('patientLoginForm');
    const formData = new FormData(form);
    const urlParams = new URLSearchParams(window.location.search);
    const redirect = urlParams.get('redirect');
    if (redirect) {
        formData.append('redirect', redirect);
    }
    const center = urlParams.get('center');
    if (center) {
        formData.append('center', center);
    }
    
    const alertBox = document.getElementById('patient-alert-box');
    const submitBtn = document.getElementById('patient-submit-btn');

    submitBtn.innerText = 'Logging in...';
    submitBtn.disabled = true;

    fetch('api/login.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            alertBox.className = 'alert success';
            alertBox.innerText = 'Login successful! Redirecting...';
            alertBox.style.display = 'block';
            setTimeout(() => { window.location.href = data.redirect; }, 1000);
        } else {
            alertBox.className = 'alert error';
            alertBox.innerText = data.message || 'Login failed.';
            alertBox.style.display = 'block';
            submitBtn.innerText = 'Log in as Patient';
            submitBtn.disabled = false;
        }
    })
    .catch(err => {
        alertBox.className = 'alert error';
        alertBox.innerText = 'A network error occurred.';
        alertBox.style.display = 'block';
        submitBtn.innerText = 'Log in as Patient';
        submitBtn.disabled = false;
    });
}

function handlePatientRegister(event) {
    event.preventDefault();
    const form = document.getElementById('registerForm');
    const formData = new FormData(form);
    const alertBox = document.getElementById('reg-alert-box');
    const submitBtn = document.getElementById('reg-submit-btn');

    submitBtn.innerText = 'Registering...';
    submitBtn.disabled = true;

    fetch('api/register.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            alertBox.className = 'alert success';
            alertBox.innerText = data.message;
            alertBox.style.display = 'block';
            form.reset();
            setTimeout(() => { 
                if (data.redirect && data.redirect !== 'index.html') {
                    window.location.href = data.redirect;
                } else {
                    window.location.href = 'dashboard_patient.php';
                }
            }, 1500);
        } else {
            alertBox.className = 'alert error';
            alertBox.innerText = data.message || 'Registration failed.';
            alertBox.style.display = 'block';
            submitBtn.innerText = 'Create Account';
            submitBtn.disabled = false;
        }
    })
    .catch(err => {
        alertBox.className = 'alert error';
        alertBox.innerText = 'A network error occurred.';
        alertBox.style.display = 'block';
        submitBtn.innerText = 'Create Account';
        submitBtn.disabled = false;
    });
}
