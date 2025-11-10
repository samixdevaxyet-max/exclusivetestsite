const API_BASE = 'http://localhost/exclusivesite/api';
let currentUser = JSON.parse(localStorage.getItem('currentUser'));

// Проверка прав администратора
if (!currentUser || currentUser.role !== 'admin') {
    alert('Доступ запрещен! Требуются права администратора.');
    window.location.href = 'index.html';
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('adminWelcome').textContent = currentUser.username + ' (Админ)';
    loadUsers();
    loadStats();
    loadActiveLicenses();
    loadAllHWIDs();
});

// Переключение в обычную панель
function switchToUserPanel() {
    window.location.href = 'dashboard.html';
}

async function loadUsers() {
    try {
        const result = await makeRequest(`${API_BASE}/users.php?action=get_all`);
        
        if (result.success) {
            const usersList = document.getElementById('usersList');
            const users = result.users;
            
            if (users.length === 0) {
                usersList.innerHTML = '<div class="empty-state">Нет пользователей</div>';
                return;
            }
            
            usersList.innerHTML = users.map(user => `
                <div class="user-item ${user.role === 'admin' ? 'admin' : 'user'}">
                    <div class="user-header">
                        <div class="user-info">
                            <strong>${user.username}</strong>
                            <span class="uid-badge">UID: ${user.uid}</span>
                        </div>
                        <select onchange="updateUserRole(${user.id}, this.value)" class="role-select">
                            <option value="user" ${user.role === 'user' ? 'selected' : ''}>User</option>
                            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                        </select>
                    </div>
                    <div class="user-details">
                        <div><i class="fas fa-envelope"></i> ${user.email}</div>
                        <div>
                            <i class="fas fa-crown"></i> Подписка: 
                            <span class="subscription-badge ${user.subscription !== 'None' ? 'active' : 'inactive'}">
                                ${user.subscription} ${user.subscriptionExpiry ? 'до ' + user.subscriptionExpiry : ''}
                            </span>
                        </div>
                        <div><i class="fas fa-fingerprint"></i> HWID: 
                            <span class="hwid-badge ${user.hwid ? 'active' : 'inactive'}">
                                ${user.hwid || 'Не привязан'}
                            </span>
                        </div>
                        <div><i class="fas fa-calendar"></i> Регистрация: ${user.registrationDate}</div>
                    </div>
                </div>
            `).join('');
        } else {
            console.error('Error loading users:', result.error);
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

// HWID функции для админки
async function loadAllHWIDs() {
    try {
        const response = await fetch(`${API_BASE}/hwid.php?action=get_all_hwids&admin=${currentUser.username}`);
        const result = await response.json();
        
        if (result.success) {
            const hwidsList = document.getElementById('hwidsList');
            const totalHWIDs = document.getElementById('totalHWIDs');
            
            totalHWIDs.textContent = result.total;
            
            if (result.hwids.length === 0) {
                hwidsList.innerHTML = '<div class="empty-state">Нет привязанных HWID</div>';
                return;
            }
            
            hwidsList.innerHTML = result.hwids.map(hwid => `
                <div class="hwid-item">
                    <div class="hwid-header">
                        <div class="hwid-user">
                            <strong>${hwid.username}</strong>
                            <span class="subscription-badge ${hwid.subscription !== 'None' ? 'active' : 'inactive'}">
                                ${hwid.subscription}
                            </span>
                        </div>
                        <button class="btn-danger btn-sm" onclick="resetUserHWID('${hwid.username}')">
                            <i class="fas fa-unlink"></i> Сбросить
                        </button>
                    </div>
                    <div class="hwid-details">
                        <div class="hwid-code">${hwid.hwid}</div>
                        <div class="hwid-date">
                            <i class="fas fa-calendar"></i> Привязан: ${hwid.bound_date}
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            console.error('Error loading HWIDs:', result.error);
        }
    } catch (error) {
        console.error('Error loading HWIDs:', error);
    }
}

async function resetUserHWID(username) {
    if (!confirm(`Вы уверены, что хотите сбросить HWID пользователя ${username}?`)) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/reset_hwid.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                username: username,
                admin_username: currentUser.username
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage(`HWID пользователя ${username} сброшен!`, 'success');
            loadAllHWIDs(); // Обновляем список
            loadUsers(); // Обновляем список пользователей
        } else {
            showMessage('Ошибка сброса HWID: ' + result.error, 'error');
        }
    } catch (error) {
        showMessage('Ошибка соединения с сервером', 'error');
    }
}

function searchHWIDs() {
    const searchTerm = document.getElementById('hwidSearch').value.toLowerCase();
    const hwidItems = document.querySelectorAll('.hwid-item');
    
    hwidItems.forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

async function updateUserRole(userId, newRole) {
    try {
        const result = await makeRequest(`${API_BASE}/users.php?action=update_role`, {
            userId: userId,
            role: newRole
        });
        
        if (result.success) {
            showMessage(`Роль пользователя обновлена на: ${newRole}`, 'success');
            loadUsers();
            
            // Обновляем текущего пользователя если это он
            if (currentUser && currentUser.id === userId) {
                currentUser.role = newRole;
                localStorage.setItem('currentUser', JSON.stringify(currentUser));
            }
        } else {
            showMessage('Ошибка обновления роли: ' + result.error, 'error');
        }
    } catch (error) {
        showMessage('Ошибка подключения к серверу', 'error');
    }
}

async function loadStats() {
    try {
        const result = await makeRequest(`${API_BASE}/users.php?action=get_stats`);
        
        if (result.success) {
            const stats = result.stats;
            
            document.getElementById('statsInfo').innerHTML = `
                <div class="stat-card">
                    <div class="stat-number">${stats.totalUsers}</div>
                    <div class="stat-label">Всего пользователей</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.adminUsers}</div>
                    <div class="stat-label">Администраторов</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.activeSubs}</div>
                    <div class="stat-label">Активных подписок</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.totalLicenses}</div>
                    <div class="stat-label">Всего ключей</div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadActiveLicenses() {
    try {
        const result = await makeRequest(`${API_BASE}/licenses.php?action=get_active`);
        
        if (result.success) {
            const activeLicenses = document.getElementById('activeLicenses');
            const licenses = result.licenses;
            
            if (licenses.length === 0) {
                activeLicenses.innerHTML = '<div class="empty-state">Нет активных ключей</div>';
                return;
            }
            
            activeLicenses.innerHTML = licenses.map(license => `
                <div class="license-item">
                    <div class="license-key">${license.key}</div>
                    <div class="license-details">
                        <span class="license-type">${license.type}</span>
                        <small>Создан: ${license.createdAt}</small>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading licenses:', error);
    }
}

async function generateKeys() {
    const type = document.getElementById('licenseType').value;
    const amount = parseInt(document.getElementById('keyAmount').value);
    const keysContainer = document.getElementById('generatedKeys');
    
    if (amount < 1 || amount > 20) {
        showMessage('Количество ключей должно быть от 1 до 20', 'error');
        return;
    }
    
    try {
        const result = await makeRequest(`${API_BASE}/licenses.php?action=generate`, {
            type: type,
            amount: amount,
            createdBy: currentUser.username
        });
        
        if (result.success) {
            const keys = result.keys;
            
            keysContainer.innerHTML = `
                <div class="keys-header">
                    <h4>Сгенерировано ключей (${type}):</h4>
                </div>
                <div class="keys-list">
                    ${keys.map(key => `
                        <div class="key-item">
                            <span class="key-text">${key}</span>
                            <button onclick="copyToClipboard('${key}')" class="btn-copy">
                                <i class="fas fa-copy"></i> Копировать
                            </button>
                        </div>
                    `).join('')}
                </div>
                <button class="btn-secondary" onclick="copyAllKeys()" style="width: 100%; margin-top: 10px;">
                    <i class="fas fa-copy"></i> Копировать все ключи
                </button>
            `;
            
            loadStats();
            loadActiveLicenses();
        } else {
            showMessage('Ошибка генерации ключей: ' + result.error, 'error');
        }
    } catch (error) {
        showMessage('Ошибка подключения к серверу', 'error');
    }
}

async function makeRequest(url, data = null) {
    try {
        const options = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
        };
        
        if (data) {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
        
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showMessage('Ключ скопирован!', 'success');
    });
}

function copyAllKeys() {
    const keys = document.querySelectorAll('.key-text');
    const keysText = Array.from(keys).map(span => span.textContent).join('\n');
    
    navigator.clipboard.writeText(keysText).then(() => {
        showMessage('Все ключи скопированы!', 'success');
    });
}

function exportData() {
    showMessage('Функция экспорта будет реализована в следующем обновлении', 'info');
}

function clearAllData() {
    if (confirm('ВНИМАНИЕ! Это удалит всех пользователей и ключи. Продолжить?')) {
        localStorage.removeItem('currentUser');
        showMessage('Локальные данные очищены!', 'success');
        setTimeout(() => window.location.href = 'index.html', 1000);
    }
}

function showMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}"></i>
        ${message}
    `;
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.classList.add('fade-out');
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.parentNode.removeChild(messageDiv);
            }
        }, 300);
    }, 4000);
}

function logout() {
    localStorage.removeItem('currentUser');
    window.location.href = 'index.html';
}