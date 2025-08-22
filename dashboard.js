// Simple tab logic for sidebar nav (only one instance)
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.dashboard-nav .nav-link[href^="#"]');
    const sections = document.querySelectorAll('.dashboard-section');
    if (navLinks.length > 0 && sections.length > 0) {
        // Hide all sections, show the first by default
        sections.forEach(s => s.classList.remove('active'));
        navLinks.forEach(l => l.classList.remove('active'));
        sections[0].classList.add('active');
        navLinks[0].classList.add('active');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    navLinks.forEach(l => l.classList.remove('active'));
                    sections.forEach(s => s.classList.remove('active'));
                    this.classList.add('active');
                    const target = this.getAttribute('href').replace('#', '');
                    const section = document.getElementById(target);
                    if (section) section.classList.add('active');
                }
            });
        });
    }

    // Profile Actions: Edit Profile & Change Password
    const profileActions = document.querySelector('.profile-actions');
    if (profileActions && !profileActions.dataset.handlersAttached) {
        profileActions.dataset.handlersAttached = '1';
        function createModal(id, html) {
            let modal = document.getElementById(id);
            if (!modal) {
                modal = document.createElement('div');
                modal.id = id;
                modal.className = 'modal-overlay';
                modal.innerHTML = `<div class="modal-content">${html}</div>`;
                document.body.appendChild(modal);
            }
            modal.style.display = 'flex';
            return modal;
        }
        function closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.style.display = 'none';
        }
        profileActions.querySelector('button:nth-child(1)').onclick = function() {
            const username = document.querySelector('.profile-info p:nth-child(1)').textContent.replace(/^[^:]+:/, '').trim();
            const email = document.querySelector('.profile-info p:nth-child(2)').textContent.replace(/^[^:]+:/, '').trim();
            const phone = document.querySelector('.profile-info p:nth-child(3)').textContent.replace(/^[^:]+:/, '').trim();
            const html = `
                <h3>Edit Profile</h3>
                <form id="edit-profile-form" style="display:flex;flex-direction:column;gap:1rem;min-width:260px;">
                    <label>Username:<input type="text" name="username" value="${username}" required></label>
                    <label>Email:<input type="email" name="email" value="${email}" required></label>
                    <label>Phone:<input type="text" name="phone" value="${phone}" required></label>
                    <div style="display:flex;gap:1rem;justify-content:flex-end;">
                        <button type="submit">Save</button>
                        <button type="button" onclick="document.getElementById('edit-profile-modal').style.display='none'">Cancel</button>
                    </div>
                </form>
                <div class="modal-msg" style="margin-top:0.7rem;color:#b00;"></div>
            `;
            const modal = createModal('edit-profile-modal', html);
            const form = modal.querySelector('#edit-profile-form');
            form.onsubmit = function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                formData.append('edit_username', formData.get('username'));
                formData.delete('username');
                formData.append('edit_email', formData.get('email'));
                formData.delete('email');
                formData.append('edit_phone', formData.get('phone'));
                formData.delete('phone');
                formData.append('ajax_edit_profile', '1');
                fetch('dashboardserver.php', { method: 'POST', body: formData })
                    .then(async res => {
                        const text = await res.text();
                        try {
                            const data = JSON.parse(text);
                            const msgDiv = modal.querySelector('.modal-msg');
                            msgDiv.textContent = data.message;
                            msgDiv.style.color = data.success ? '#1a7f2e' : '#b00';
                            if (data.success) {
                                document.querySelector('.profile-info p:nth-child(1)').innerHTML = '<strong>Username:</strong> ' + form.username.value;
                                document.querySelector('.profile-info p:nth-child(2)').innerHTML = '<strong>Email:</strong> ' + form.email.value;
                                document.querySelector('.profile-info p:nth-child(3)').innerHTML = '<strong>Phone:</strong> ' + form.phone.value;
                                setTimeout(() => closeModal('edit-profile-modal'), 1200);
                            }
                        } catch (e) {
                            alert('Network error. Please try again.');
                            console.error('Response was:', text);
                        }
                    })
                    .catch(err => {
                        alert('Network error. Please try again.');
                        console.error(err);
                    });
            };
        };
        profileActions.querySelector('button:nth-child(2)').onclick = function() {
            const html = `
                <h3>Change Password</h3>
                <form id="change-password-form" style="display:flex;flex-direction:column;gap:1rem;min-width:260px;">
                    <label>Current Password:<input type="password" name="current_password" required></label>
                    <label>New Password:<input type="password" name="new_password" required></label>
                    <label>Confirm Password:<input type="password" name="confirm_password" required></label>
                    <div style="display:flex;gap:1rem;justify-content:flex-end;">
                        <button type="submit">Change</button>
                        <button type="button" onclick="document.getElementById('change-password-modal').style.display='none'">Cancel</button>
                    </div>
                </form>
                <div class="modal-msg" style="margin-top:0.7rem;color:#b00;"></div>
            `;
            const modal = createModal('change-password-modal', html);
            const form = modal.querySelector('#change-password-form');
            form.onsubmit = function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                formData.append('change_password', '1');
                fetch('dashboardserver.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        const msgDiv = modal.querySelector('.modal-msg');
                        msgDiv.textContent = data.message;
                        msgDiv.style.color = data.success ? '#1a7f2e' : '#b00';
                        if (data.success) setTimeout(() => closeModal('change-password-modal'), 1200);
                    });
            };
        };
        if (!document.getElementById('modal-style')) {
            const style = document.createElement('style');
            style.id = 'modal-style';
            style.textContent = `
            .modal-overlay { position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(44,62,80,0.18);display:flex;align-items:center;justify-content:center;z-index:9999; }
            .modal-content { background:#fff;border-radius:1rem;box-shadow:0 2px 16px rgba(44,62,80,0.13);padding:2rem 2.2rem;min-width:260px;max-width:95vw; }
            .modal-content button { background:#1a237e;color:#fff;border:none;border-radius:0.7rem;padding:0.7rem 1.5rem;font-size:1.08rem;font-weight:700;cursor:pointer;transition:background 0.2s; }
            .modal-content button:hover { background:#23395d; }
            .modal-msg { font-size:0.98rem; min-height:1.2em; }
            `;
            document.head.appendChild(style);
        }
    }
   
document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation logic
    const navLinks = document.querySelectorAll('.dashboard-nav a[href^="#"]');
    const sections = document.querySelectorAll('.dashboard-section');
    function showSection(id) {
        sections.forEach(sec => {
            if (sec.id === id) {
                sec.classList.add('active');
                sec.style.display = 'block';
            } else {
                sec.classList.remove('active');
                sec.style.display = 'none';
            }
        });
        navLinks.forEach(link => {
            link.classList.toggle('active', link.getAttribute('href') === '#' + id);
        });
    }
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.getAttribute('href').startsWith('#')) {
                e.preventDefault();
                showSection(this.getAttribute('href').substring(1));
            }
        });
    });
    // Show the first visible section by default
    let firstSection = document.querySelector('.dashboard-section');
    if (firstSection) {
        showSection(firstSection.id);

    }

   
});


    
// Ensure the sections are wrapped in a container for better layout control
    // Withdrawal AJAX submission (DISABLED: handled in dashboard.php, not here)
    /*
    const withdrawalForm = document.getElementById('withdrawal-form');
    if (withdrawalForm) {
        withdrawalForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = parseFloat(document.getElementById('withdraw_amount').value);
            const bank = document.getElementById('bank_name').value.trim();
            const acctNum = document.getElementById('account_number').value.trim();
            const acctName = document.getElementById('account_name').value.trim();
            let error = '';
            if (isNaN(amount) || amount < 1000) error = 'Minimum withdrawal amount is 1,000.';
            else if (!bank) error = 'Please enter your bank name.';
            else if (!/^[\d]{10}$/.test(acctNum)) error = 'Account number must be 10 digits.';
            else if (!acctName) error = 'Please enter the account holder\'s name.';
            if (error) {
                alert(error);
                return false;
            }
            const formData = new FormData(withdrawalForm);
            formData.append('withdraw_funds', '1');
            fetch('dashboardserver.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    withdrawalForm.reset();
                    if (typeof refreshPortfolioSummary === 'function') refreshPortfolioSummary();
                }
            })
            .catch(() => alert('An error occurred. Please try again.'));
        });
    }
    */
});





