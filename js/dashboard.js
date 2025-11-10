const API_BASE = 'http://localhost/exclusivesite/api';
let currentUser = JSON.parse(localStorage.getItem('currentUser'));

if (!currentUser) {
    alert('Требуется авторизация!');
    window.location.href = 'index.html';
}

document.addEventListener('DOMContentLoaded', function() {
    updateUserInfo();
    loadHWIDInfo();
    
    // Показываем кнопку переключения в админ панель если пользователь админ
    if (currentUser.role === 'admin') {
        document.getElementById('adminPanelSwitch').style.display = 'block';
    }
    
    // Скрываем скачивание если нет подписки
    if (currentUser.subscription === 'None') {
        const downloadSection = document.querySelector('.download-section');
        if (downloadSection) {
            downloadSection.style.display = 'none';
        }
    } else {
        const downloadBtn = document.getElementById('downloadBtn');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', function() {
                const downloadInfo = document.getElementById('downloadInfo');
                if (downloadInfo) {
                    downloadInfo.style.display = 'block';
                }
                copyToClipboard(document.getElementById('archivePassword').textContent);
                showTempMessage('Пароль скопирован в буфер обмена!', 'success');
            });
        }
    }

    loadActivationHistory();
    loadUserStats();

    // Обработчик Enter для поля ввода ключа
    const keyInput = document.getElementById('licenseKey');
    if (keyInput) {
        keyInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                activateLicense();
            }
        });
    }
});

function updateUserInfo() {
    const accountInfo = document.getElementById('accountInfo');
    const userInfo = document.getElementById('userInfo');
    
    if (userInfo) {
        userInfo.innerHTML = `Добро пожаловать, <strong>${currentUser.username}</strong>!`;
    }
    
    if (accountInfo) {
        accountInfo.innerHTML = `
            <div class="account-details">
                <div class="detail-row">
                    <div class="detail-item">
                        <strong>UID:</strong>
                        <span class="uid-badge">${currentUser.uid || 'N/A'}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Дата регистрации:</strong>
                        <span>${currentUser.registrationDate || 'Не указана'}</span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-item">
                        <strong>Роль:</strong>
                        <span class="role-badge ${currentUser.role}">
                            ${currentUser.role === 'admin' ? '👑 Администратор' : '👤 Пользователь'}
                        </span>
                    </div>
                    <div class="detail-item">
                        <strong>Подписка:</strong>
                        <span class="subscription-badge ${currentUser.subscription !== 'None' ? 'active' : 'inactive'}">
                            ${currentUser.subscription !== 'None' ? '✅ Активна' : '❌ Не активна'}
                        </span>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-item">
                        <strong>Email:</strong>
                        <span>${currentUser.email || 'Не указан'}</span>
                    </div>
                    <div class="detail-item">
                        <strong>Логин:</strong>
                        <span>${currentUser.username}</span>
                    </div>
                </div>
                ${currentUser.subscriptionExpiry ? `
                <div class="detail-row">
                    <div class="detail-item">
                        <strong>Подписка до:</strong>
                        <span>${currentUser.subscriptionExpiry}</span>
                    </div>
                </div>
                ` : ''}
            </div>
        `;
    }
}

// HWID функции
async function loadHWIDInfo() {
    try {
        const response = await fetch(`${API_BASE}/hwid.php?action=get_hwid&username=${currentUser.username}`);
        const result = await response.json();
        
        if (result.success) {
            const hwidStatus = document.getElementById('hwidStatus');
            const hwidValue = document.getElementById('hwidValue');
            const hwidDate = document.getElementById('hwidDate');
            const resetBtn = document.getElementById('resetHwidBtn');
            
            if (result.hwid) {
                // HWID привязан
                hwidStatus.textContent = '✅ Привязан';
                hwidStatus.className = 'hwid-status active';
                hwidValue.textContent = result.hwid;
                hwidDate.textContent = result.hwid_bound_date || 'Неизвестно';
                
                // Показываем кнопку сброса только для админа
                if (currentUser.role === 'admin') {
                    resetBtn.style.display = 'block';
                }
            } else {
                // HWID не привязан
                hwidStatus.textContent = '❌ Не привязан';
                hwidStatus.className = 'hwid-status inactive';
                hwidValue.textContent = 'Не привязан';
                hwidDate.textContent = '—';
                resetBtn.style.display = 'none';
            }
        } else {
            showTempMessage('Ошибка загрузки HWID: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error loading HWID:', error);
        showTempMessage('Ошибка загрузки HWID информации', 'error');
    }
}

function copyHWID() {
    const hwidValue = document.getElementById('hwidValue');
    const hwidText = hwidValue.textContent;
    
    if (hwidText && hwidText !== 'Загрузка...' && hwidText !== 'Не привязан') {
        copyToClipboard(hwidText);
        showTempMessage('HWID скопирован в буфер обмена!', 'success');
    } else {
        showTempMessage('HWID не привязан', 'error');
    }
}

function showResetHWIDModal() {
    const modal = document.getElementById('resetHwidModal');
    const currentHwid = document.getElementById('hwidValue').textContent;
    
    document.getElementById('currentHwidDisplay').textContent = currentHwid;
    modal.style.display = 'block';
}

function closeResetHWIDModal() {
    const modal = document.getElementById('resetHwidModal');
    modal.style.display = 'none';
}

async function resetHWID() {
    try {
        const response = await fetch(`${API_BASE}/reset_hwid.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                username: currentUser.username,
                admin_username: currentUser.username
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showTempMessage('HWID успешно сброшен!', 'success');
            closeResetHWIDModal();
            loadHWIDInfo(); // Обновляем информацию
        } else {
            showTempMessage('Ошибка сброса HWID: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error resetting HWID:', error);
        showTempMessage('Ошибка соединения с сервером', 'error');
    }
}

// Переключение в админ панель
function switchToAdminPanel() {
    window.location.href = 'admin.html';
}

async function activateLicense() {
    const keyInput = document.getElementById('licenseKey');
    const key = keyInput.value.trim().toUpperCase();
    
    if (!key) {
        showTempMessage('Введите лицензионный ключ!', 'error');
        return;
    }

    const keyRegex = /^[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/;
    if (!keyRegex.test(key)) {
        showTempMessage('Неверный формат ключа! Формат: XXXXX-XXXXX-XXXXX-XXXXX', 'error');
        return;
    }
    
    try {
        const result = await makeRequest(`${API_BASE}/licenses.php?action=activate`, {
            key: key,
            username: currentUser.username
        });
        
        if (result.success) {
            showTempMessage(`Подписка "${result.subscription}" успешно активирована до ${result.expiryDate}!`, 'success');
            keyInput.value = '';
            
            // ОБНОВЛЯЕМ данные пользователя с сервера
            await refreshUserData();
            
            updateUserInfo();
            saveToActivationHistory(key, result.subscription, result.expiryDate);
            
            // Показываем секцию скачивания
            const downloadSection = document.querySelector('.download-section');
            if (downloadSection) {
                downloadSection.style.display = 'block';
            }
        } else {
            showTempMessage('Ошибка: ' + result.error, 'error');
        }
    } catch (error) {
        showTempMessage('Ошибка подключения к серверу', 'error');
        console.error('Activation error:', error);
    }
}

// НОВЫЙ МЕТОД: Обновление данных пользователя с сервера
async function refreshUserData() {
    try {
        const result = await makeRequest(`${API_BASE}/get_user_info.php?username=${currentUser.username}`);
        
        if (result.success) {
            // Обновляем текущего пользователя
            currentUser = {
                ...currentUser,
                ...result.user
            };
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            console.log('User data refreshed from server');
        }
    } catch (error) {
        console.error('Error refreshing user data:', error);
    }
}

async function makeRequest(url, data = null) {
    try {
        const options = {
            method: data ? 'POST' : 'GET',
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

function saveToActivationHistory(key, type, expiryDate) {
    const history = JSON.parse(localStorage.getItem('activationHistory')) || [];
    history.unshift({
        key: key,
        type: type,
        expiryDate: expiryDate,
        date: new Date().toLocaleString('ru-RU'),
        username: currentUser.username
    });
    
    if (history.length > 10) {
        history.pop();
    }
    
    localStorage.setItem('activationHistory', JSON.stringify(history));
    loadActivationHistory();
}

function loadActivationHistory() {
    const history = JSON.parse(localStorage.getItem('activationHistory')) || [];
    const historyContainer = document.getElementById('activationHistory');
    
    if (!historyContainer) return;
    
    if (history.length === 0) {
        historyContainer.innerHTML = '<div class="empty-state">Нет истории активаций</div>';
        return;
    }
    
    historyContainer.innerHTML = history.map(entry => `
        <div class="history-item">
            <div class="history-header">
                <strong class="license-key">${entry.key}</strong>
                <span class="license-type">${entry.type}</span>
            </div>
            <div class="history-date">
                <i class="fas fa-clock"></i> ${entry.date} | ${entry.username}
            </div>
            ${entry.expiryDate ? `
            <div class="history-expiry">
                <i class="fas fa-calendar"></i> Действует до: ${entry.expiryDate}
            </div>
            ` : ''}
        </div>
    `).join('');
}

function loadUserStats() {
    // Имитация статистики пользователя
    const usageCount = Math.floor(Math.random() * 50) + 10;
    const sessionTime = Math.floor(Math.random() * 24) + 1;
    const lastActive = new Date().toLocaleDateString('ru-RU');
    
    document.getElementById('usageCount').textContent = usageCount;
    document.getElementById('sessionTime').textContent = sessionTime + 'ч';
    document.getElementById('lastActive').textContent = lastActive;
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        console.log('Текст скопирован: ' + text);
    }).catch(err => {
        console.error('Ошибка копирования: ', err);
    });
}

function copyPassword() {
    copyToClipboard(document.getElementById('archivePassword').textContent);
    showTempMessage('Пароль скопирован!', 'success');
}

function showTempMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `temp-message ${type}`;
    messageDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}"></i>
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
    }, 3000);
}

function contactSupport() {
    alert('Для связи с поддержкой: support@exclusive.ru\nМы ответим в течение 24 часов.');
}

function logout() {
    localStorage.removeItem('currentUser');
    window.location.href = 'index.html';
}