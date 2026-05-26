document.addEventListener('DOMContentLoaded', () => {
    // Load default centers immediately so the user sees them when arriving
    fetchCenters(); 
});

function requestLocation() {
    const btn = document.getElementById('btn-gps');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Locating...';
    
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                document.getElementById('centers-title').innerText = "Medical Centers Near You";
                fetchCenters(`lat=${lat}&lng=${lng}`);
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Location Found';
                btn.style.backgroundColor = '#10b981';
            },
            (error) => {
                console.warn("Location error:", error);
                alert("Location access denied or failed. Please enter your city manually.");
                document.getElementById('fallback-ui').style.display = 'flex';
                btn.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Use My Current Location';
            }
        );
    } else {
        alert("Geolocation is not supported by this browser.");
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

function fetchCenters(queryString = '') {
    const container = document.getElementById('centers-container');
    container.innerHTML = '<p style="grid-column: 1/-1; text-align:center;">Loading centers...</p>';
    
    fetch(`api/get_centers.php?${queryString}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data.length > 0) {
                container.innerHTML = '';
                
                // Mock business hours logic
                const currentHour = new Date().getHours();
                const isOpen = currentHour >= 8 && currentHour < 20; // Open 8 AM to 8 PM
                
                data.data.forEach(center => {
                    let distanceHtml = '';
                    if (center.distance) {
                        distanceHtml = `<div class="distance-badge"><i class="fa-solid fa-location-dot"></i> ${parseFloat(center.distance).toFixed(2)} km away</div>`;
                    }
                    
                    const statusHtml = isOpen ? 
                        `<span style="color: #10b981; font-weight: 600; font-size: 0.9rem;"><i class="fa-solid fa-door-open"></i> Open Now</span>` : 
                        `<span style="color: #ef4444; font-weight: 600; font-size: 0.9rem;"><i class="fa-solid fa-door-closed"></i> Closed</span>`;

                    const mapIframe = `<iframe 
                        width="100%" 
                        height="200" 
                        style="border:0; border-radius: 8px; margin-top: 1rem;" 
                        loading="lazy" 
                        allowfullscreen 
                        src="https://maps.google.com/maps?q=${center.latitude},${center.longitude}&z=15&output=embed">
                    </iframe>`;
                    
                    const card = document.createElement('div');
                    card.className = 'center-card';
                    card.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                            <h3 style="margin: 0;">${center.name}</h3>
                            ${statusHtml}
                        </div>
                        <p><i class="fa-solid fa-map-pin"></i> ${center.address}, ${center.city}</p>
                        <p><i class="fa-solid fa-phone"></i> ${center.contact_number || 'N/A'}</p>
                        ${distanceHtml}
                        ${mapIframe}
                        <button class="btn-primary" style="margin-top: 1rem; width: 100%; padding: 0.75rem;" onclick="goToRegistration('${center.name.replace(/'/g, "\\'")}')">Book Appointment</button>
                    `;
                    container.appendChild(card);
                });
            } else {
                container.innerHTML = '<p style="grid-column: 1/-1; text-align:center;">No centers found matching your criteria.</p>';
            }
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<p style="grid-column: 1/-1; text-align:center; color: red;">Failed to load data.</p>';
        });
}

function goToRegistration(centerName) {
    window.location.href = `register.html?center=${encodeURIComponent(centerName)}`;
}

function viewDoctors(centerId, centerName) {
    const modal = document.getElementById('doctor-modal');
    const doctorList = document.getElementById('modal-doctor-list');
    document.getElementById('modal-center-name').innerText = centerName;
    
    doctorList.innerHTML = '<p>Loading doctors...</p>';
    modal.style.display = 'flex';

    fetch(`api/get_doctors.php?center_id=${centerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data.length > 0) {
                doctorList.innerHTML = '';
                
                // Group by doctor
                const docs = {};
                data.data.forEach(row => {
                    if (!docs[row.doctor_id]) {
                        docs[row.doctor_id] = { name: row.first_name + ' ' + row.last_name, spec: row.specialization, sessions: [] };
                    }
                    if (row.availability_id) {
                        docs[row.doctor_id].sessions.push({ id: row.availability_id, date: row.date, start: row.start_time, end: row.end_time });
                    }
                });

                Object.keys(docs).forEach(docId => {
                    const doc = docs[docId];
                    let sessionsHtml = '';
                    if (doc.sessions.length > 0) {
                        sessionsHtml = doc.sessions.map(s => `<div style="background: #f1f5f9; padding: 0.4rem; margin-top: 0.25rem; font-size: 0.85rem; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                            <span>${s.date} | ${s.start} - ${s.end}</span>
                            <button class="btn-primary" style="padding: 0.2rem 0.5rem; font-size: 0.8rem;" onclick="bookAppointment(${centerId}, ${docId}, ${s.id})">Book</button>
                        </div>`).join('');
                    } else {
                        sessionsHtml = `<p style="font-size: 0.85rem; color: #ef4444; margin-top: 0.25rem;">No available sessions currently.</p>`;
                    }
                    
                    doctorList.innerHTML += `
                        <div class="doctor-list-item" style="display: block;">
                            <h4 style="color: var(--secondary);">Dr. ${doc.name}</h4>
                            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;">${doc.spec}</p>
                            ${sessionsHtml}
                        </div>
                    `;
                });
            } else {
                doctorList.innerHTML = '<p>No doctors available at this center currently.</p>';
            }
        })
        .catch(err => {
            console.error(err);
            doctorList.innerHTML = '<p style="color: red;">Failed to load doctors.</p>';
        });
}

function closeModal() {
    document.getElementById('doctor-modal').style.display = 'none';
}

function bookAppointment(centerId, doctorId, availabilityId) {
    alert("Authentication Required! You will be redirected to the login page to complete your booking.");
    window.location.href = `login.html?role=patient&redirect=booking&center=${centerId}&doctor=${doctorId}&avail=${availabilityId}`;
}

// Map logic removed as per new requirements
