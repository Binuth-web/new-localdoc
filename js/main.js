let patientLoggedIn = false;

document.addEventListener('DOMContentLoaded', async () => {
    await initSessionNav();
    fetchCenters();

    const params = new URLSearchParams(window.location.search);
    const bookCenter = params.get('book_center');
    if (bookCenter) {
        setTimeout(() => openBookingForCenter(parseInt(bookCenter, 10)), 800);
    }
});

function initSessionNav() {
    return fetch('api/session_status.php')
        .then((r) => r.json())
        .then((data) => {
            patientLoggedIn = data.is_patient === true;
            if (!patientLoggedIn) return;

            const nav = document.getElementById('main-nav');
            nav.innerHTML = `
                <a href="#centers-section">Get Appointment</a>
                <a href="dashboard_patient.php">My Appointments</a>
                <a href="profile.php">My Profile</a>
                <span style="font-weight: 600; margin-right: 0.5rem;">Hello, ${escapeHtml(data.name || 'Patient')}</span>
                <a href="api/logout.php" class="btn-login" style="background: transparent; color: var(--primary) !important; border: 2px solid var(--primary);">Logout</a>
            `;

            const quickAppointments = document.getElementById('quick-appointments');
            const quickProfile = document.getElementById('quick-profile');
            const quickLogin = document.getElementById('quick-login');
            if (quickAppointments) quickAppointments.href = 'dashboard_patient.php';
            if (quickProfile) quickProfile.href = 'profile.php';
            if (quickLogin) {
                quickLogin.href = 'api/logout.php';
                quickLogin.querySelector('span').textContent = 'Logout';
                quickLogin.querySelector('i').className = 'fa-solid fa-right-from-bracket';
            }

            const locationPrompt = document.getElementById('location-prompt-container');
            if (locationPrompt) {
                locationPrompt.style.display = 'block';
            }
        })
        .catch(() => {});
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function requestLocation() {
    const btn = document.getElementById('btn-gps');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Locating...';

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                document.getElementById('centers-title').innerText = 'Medical Centers Near You';
                fetchCenters(`lat=${lat}&lng=${lng}`);
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Location Found';
                btn.style.backgroundColor = '#10b981';
            },
            () => {
                alert('Location access denied or failed. Please enter your city manually.');
                document.getElementById('fallback-ui').style.display = 'flex';
                btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Use My Current Location';
            }
        );
    } else {
        alert('Geolocation is not supported by this browser.');
        document.getElementById('fallback-ui').style.display = 'flex';
    }
}

function searchByCity() {
    const city = document.getElementById('city-input').value.trim();
    if (city) {
        document.getElementById('centers-title').innerText = `Medical Centers in ${city}`;
        fetchCenters(`city=${encodeURIComponent(city)}`);
    }
}

function isCenterOpen(center) {
    if (center.available === false || center.available === 0) {
        return false;
    }

    const now = new Date();
    const minutesNow = now.getHours() * 60 + now.getMinutes();
    const openParts = (center.open_time || '08:00:00').split(':');
    const closeParts = (center.close_time || '20:00:00').split(':');
    const openMins = parseInt(openParts[0], 10) * 60 + parseInt(openParts[1], 10);
    const closeMins = parseInt(closeParts[0], 10) * 60 + parseInt(closeParts[1], 10);

    return minutesNow >= openMins && minutesNow < closeMins;
}

function formatTime(timeStr) {
    if (!timeStr) return '';
    const [h, m] = timeStr.split(':');
    const hour = parseInt(h, 10);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${m} ${ampm}`;
}

function fetchCenters(queryString = '') {
    const container = document.getElementById('centers-container');
    container.innerHTML = '<p class="centers-loading">Loading medical centers…</p>';

    fetch(`api/get_centers.php?${queryString}`)
        .then((response) => response.json())
        .then((data) => {
            if (data.status === 'success' && data.data.length > 0) {
                container.innerHTML = '';
                data.data.forEach((center) => renderCenterCard(container, center));
            } else {
                container.innerHTML = '<p class="centers-loading">No centers found matching your criteria.</p>';
            }
        })
        .catch((err) => {
            console.error(err);
            container.innerHTML = '<p class="centers-loading" style="color: #ef4444;">Failed to load medical centers.</p>';
        });
}

function renderCenterCard(container, center) {
    const open = isCenterOpen(center);
    const statusHtml = open
        ? '<span class="status-open"><i class="fa-solid fa-door-open"></i> Open Now</span>'
        : '<span class="status-closed"><i class="fa-solid fa-door-closed"></i> Closed</span>';

    const hoursLabel = center.hours || `${formatTime(center.open_time)} – ${formatTime(center.close_time)}`;

    let distanceHtml = '';
    if (center.distance) {
        distanceHtml = `<div class="distance-badge"><i class="fa-solid fa-location-dot"></i> ${parseFloat(center.distance).toFixed(2)} km away</div>`;
    }

    const mapsLink = `https://www.google.com/maps?q=${center.latitude},${center.longitude}`;
    const mapIframe = `<iframe
        width="100%"
        height="200"
        class="center-map"
        loading="lazy"
        allowfullscreen
        referrerpolicy="no-referrer-when-downgrade"
        src="https://maps.google.com/maps?q=${center.latitude},${center.longitude}&z=15&output=embed">
    </iframe>`;

    const phone = center.contact_number || 'Contact not listed';
    const phoneHtml = center.contact_number
        ? `<a href="tel:${center.contact_number.replace(/\s/g, '')}" class="center-phone"><i class="fa-solid fa-phone"></i> ${escapeHtml(center.contact_number)}</a>`
        : `<p><i class="fa-solid fa-phone"></i> ${phone}</p>`;

    const card = document.createElement('div');
    card.className = 'center-card';
    card.dataset.centerId = center.center_id;
    card.innerHTML = `
        <div class="center-card-header">
            <h3>${escapeHtml(center.name)}</h3>
            ${statusHtml}
        </div>
        <p><i class="fa-solid fa-map-pin"></i> ${escapeHtml(center.address)}, ${escapeHtml(center.city)}</p>
        ${phoneHtml}
        <p class="center-hours"><i class="fa-solid fa-clock"></i> ${hoursLabel}</p>
        ${distanceHtml}
        ${mapIframe}
        <a href="${mapsLink}" target="_blank" rel="noopener" class="maps-external-link">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open in Google Maps
        </a>
        <button type="button" class="btn-primary center-book-btn" data-center-id="${center.center_id}" data-center-name="${escapeHtml(center.name)}">
            Book Appointment
        </button>
    `;

    card.querySelector('.center-book-btn').addEventListener('click', () => {
        handleBookAppointment(center.center_id, center.name);
    });

    container.appendChild(card);
}

function handleBookAppointment(centerId, centerName) {
    if (patientLoggedIn) {
        viewDoctors(centerId, centerName);
        return;
    }
    goToRegistration(centerId, centerName);
}

function goToRegistration(centerId, centerName) {
    window.location.href = `register.html?center_id=${centerId}&center=${encodeURIComponent(centerName)}`;
}

function openBookingForCenter(centerId) {
    const card = document.querySelector(`.center-card[data-center-id="${centerId}"]`);
    if (card) {
        const name = card.querySelector('h3')?.textContent || 'Medical Center';
        viewDoctors(centerId, name);
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    fetch(`api/get_centers.php`)
        .then((r) => r.json())
        .then((data) => {
            const center = data.data?.find((c) => c.center_id == centerId);
            if (center) viewDoctors(centerId, center.name);
        });
}

function viewDoctors(centerId, centerName) {
    const modal = document.getElementById('doctor-modal');
    const doctorList = document.getElementById('modal-doctor-list');
    document.getElementById('modal-center-name').innerText = centerName;

    doctorList.innerHTML = '<p>Loading doctors…</p>';
    modal.style.display = 'flex';

    fetch(`api/get_doctors.php?center_id=${centerId}`)
        .then((response) => response.json())
        .then((data) => {
            if (data.status === 'success' && data.data.length > 0) {
                doctorList.innerHTML = '';
                const docs = {};
                data.data.forEach((row) => {
                    if (!docs[row.doctor_id]) {
                        docs[row.doctor_id] = {
                            name: row.first_name + ' ' + row.last_name,
                            spec: row.specialization,
                            sessions: [],
                        };
                    }
                    if (row.availability_id) {
                        docs[row.doctor_id].sessions.push({
                            id: row.availability_id,
                            date: row.date,
                            start: row.start_time,
                            end: row.end_time,
                        });
                    }
                });

                Object.keys(docs).forEach((docId) => {
                    const doc = docs[docId];
                    let sessionsHtml = '';
                    if (doc.sessions.length > 0) {
                        sessionsHtml = doc.sessions
                            .map(
                                (s) => `<div class="session-row">
                            <span>${s.date} | ${s.start} – ${s.end}</span>
                            <button type="button" class="btn-primary session-book-btn" data-avail="${s.id}" data-doctor="${docId}" data-center="${centerId}">Book</button>
                        </div>`
                            )
                            .join('');
                    } else {
                        sessionsHtml =
                            '<p style="font-size: 0.85rem; color: #ef4444; margin-top: 0.25rem;">No available sessions currently.</p>';
                    }

                    const block = document.createElement('div');
                    block.className = 'doctor-list-item';
                    block.style.display = 'block';
                    const displayName = doc.name.trim();
                    const docLabel =
                        displayName.split(/\s+/).length > 1
                            ? `Dr. ${escapeHtml(displayName)}`
                            : escapeHtml(displayName);
                    block.innerHTML = `
                        <h4 style="color: var(--secondary);">${docLabel}</h4>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;">${escapeHtml(doc.spec)}</p>
                        ${sessionsHtml}
                    `;
                    block.querySelectorAll('.session-book-btn').forEach((btn) => {
                        btn.addEventListener('click', () =>
                            bookAppointment(centerId, parseInt(btn.dataset.doctor, 10), parseInt(btn.dataset.avail, 10))
                        );
                    });
                    doctorList.appendChild(block);
                });
            } else {
                doctorList.innerHTML = '<p>No doctors available at this center currently.</p>';
            }
        })
        .catch((err) => {
            console.error(err);
            doctorList.innerHTML = '<p style="color: red;">Failed to load doctors.</p>';
        });
}

function closeModal() {
    document.getElementById('doctor-modal').style.display = 'none';
}

function bookAppointment(centerId, doctorId, availabilityId) {
    if (!patientLoggedIn) {
        window.location.href = `register.html?center_id=${centerId}&avail=${availabilityId}`;
        return;
    }

    const formData = new FormData();
    formData.append('availability_id', availabilityId);

    fetch('api/book_appointment.php', { method: 'POST', body: formData })
        .then((r) => r.json())
        .then((data) => {
            if (data.status === 'success') {
                closeModal();
                alert(data.message);
                window.location.href = data.redirect || 'dashboard_patient.php';
            } else {
                alert(data.message || 'Booking failed.');
            }
        })
        .catch(() => alert('Network error while booking.'));
}
