// Конфигурация API
const API_BASE = 'http://localhost/exclusivesite/api';
let currentUser = null;

// Глобальная функция проверки авторизации
function checkAuth() {
    const savedUser = localStorage.getItem('currentUser');
    if (savedUser) {
        try {
            currentUser = JSON.parse(savedUser);
            updateNavigation();
            return true;
        } catch (e) {
            console.error('Error parsing saved user:', e);
            localStorage.removeItem('currentUser');
            return false;
        }
    }
    return false;
}

// Проверяем авторизацию при загрузке
document.addEventListener('DOMContentLoaded', function() {
    checkAuth();
});

// Модальное окно авторизации
function openAuthModal(type) {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.style.display = 'block';
        if (type === 'login') {
            switchTab('login');
        } else {
            switchTab('register');
        }
    }
}

function closeAuthModal() {
    const modal = document.getElementById('authModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn) btn.classList.remove('active');
    });
    document.querySelectorAll('.auth-form').forEach(form => {
        if (form) form.classList.remove('active');
    });
    
    const loginTab = document.querySelector('.tab-btn:nth-child(1)');
    const registerTab = document.querySelector('.tab-btn:nth-child(2)');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    if (tabName === 'login') {
        if (loginTab) loginTab.classList.add('active');
        if (loginForm) loginForm.classList.add('active');
    } else {
        if (registerTab) registerTab.classList.add('active');
        if (registerForm) registerForm.classList.add('active');
    }
}

// Обработка форм
const loginForm = document.getElementById('loginForm');
const registerForm = document.getElementById('registerForm');

if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const username = this.querySelector('input[type="text"]').value;
        const password = this.querySelector('input[type="password"]').value;
        
        if (!username || !password) {
            showMessage('Заполните все поля!', 'error');
            return;
        }
        
        loginUser(username, password);
    });
}

if (registerForm) {
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const username = this.querySelector('input[type="text"]').value;
        const email = this.querySelector('input[type="email"]').value;
        const password = this.querySelector('input[type="password"]').value;
        
        if (!username || !email || !password) {
            showMessage('Заполните все поля!', 'error');
            return;
        }
        
        if (password.length < 4) {
            showMessage('Пароль должен быть не менее 4 символов!', 'error');
            return;
        }
        
        registerUser(username, email, password);
    });
}

// API функции
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
        return { 
            success: false, 
            error: 'Не удалось подключиться к серверу. Убедитесь что XAMPP запущен.' 
        };
    }
}

async function loginUser(username, password) {
    const loginBtn = document.querySelector('#loginForm .btn-primary');
    const originalText = loginBtn ? loginBtn.textContent : '';
    
    if (loginBtn) {
        loginBtn.disabled = true;
        loginBtn.textContent = 'Вход...';
    }
    
    const result = await makeRequest(`${API_BASE}/auth.php?action=login`, {
        username, password
    });
    
    if (loginBtn) {
        loginBtn.disabled = false;
        loginBtn.textContent = originalText;
    }
    
    if (result.success) {
        currentUser = result.user;
        localStorage.setItem('currentUser', JSON.stringify(currentUser));
        closeAuthModal();
        updateNavigation();
        
        showMessage(`Добро пожаловать, ${username}!`, 'success');
        
        // Перенаправление
        setTimeout(() => {
            if (currentUser.role === 'admin') {
                window.location.href = 'admin.html';
            } else {
                window.location.href = 'dashboard.html';
            }
        }, 1000);
        
    } else {
        showMessage('Ошибка авторизации: ' + (result.error || 'Неверный логин или пароль'), 'error');
    }
}

async function registerUser(username, email, password) {
    const registerBtn = document.querySelector('#registerForm .btn-primary');
    const originalText = registerBtn ? registerBtn.textContent : '';
    
    if (registerBtn) {
        registerBtn.disabled = true;
        registerBtn.textContent = 'Регистрация...';
    }
    
    const result = await makeRequest(`${API_BASE}/register.php`, {
        username, email, password
    });
    
    if (registerBtn) {
        registerBtn.disabled = false;
        registerBtn.textContent = originalText;
    }
    
    if (result.success) {
        showMessage('Регистрация успешна! Теперь войдите в аккаунт.', 'success');
        
        // Очищаем форму и переключаем на вход
        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.reset();
        }
        switchTab('login');
    } else {
        showMessage('Ошибка регистрации: ' + (result.error || 'Неизвестная ошибка'), 'error');
    }
}

function updateNavigation() {
    const authButtons = document.querySelector('.auth-buttons');
    if (!authButtons) return;
    
    if (currentUser) {
        authButtons.innerHTML = `
            <span style="color: #ffffff; margin-right: 15px;">
                ${currentUser.username} (${currentUser.role === 'admin' ? 'Админ' : 'Пользователь'})
            </span>
            <button class="btn-login" onclick="logout()">Выйти</button>
        `;
    } else {
        authButtons.innerHTML = `
            <button class="btn-login" onclick="openAuthModal('login')">Войти</button>
            <button class="btn-register" onclick="openAuthModal('register')">Регистрация</button>
        `;
    }
}

function logout() {
    currentUser = null;
    localStorage.removeItem('currentUser');
    showMessage('Вы вышли из системы', 'success');
    setTimeout(() => {
        window.location.href = 'index.html';
    }, 1000);
}

function showMessage(message, type) {
    let messageContainer = document.getElementById('messageContainer');
    if (!messageContainer) {
        messageContainer = document.createElement('div');
        messageContainer.id = 'messageContainer';
        messageContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
        `;
        document.body.appendChild(messageContainer);
    }
    
    const messageDiv = document.createElement('div');
    messageDiv.textContent = message;
    messageDiv.style.cssText = `
        background: ${type === 'success' ? '#4CAF50' : '#666'};
        color: white;
        padding: 15px 20px;
        margin-bottom: 10px;
        border-radius: 8px;
        font-weight: 500;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        animation: slideInRight 0.3s ease;
    `;
    
    messageContainer.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.parentNode.removeChild(messageDiv);
            }
        }, 300);
    }, 4000);
}

// Закрытие модального окна при клике вне его
window.onclick = function(event) {
    const modal = document.getElementById('authModal');
    if (event.target === modal) {
        closeAuthModal();
    }
}

// Добавляем CSS для анимаций
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Плавная прокрутка для навигации
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});