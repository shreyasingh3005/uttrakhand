/* ==================== API UTILITIES ==================== */

const API_BASE = '/router.php/api';

// Make API request
async function apiRequest(endpoint, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };

    if (data) {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(`${API_BASE}${endpoint}`, options);
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('API Error:', error);
        showAlert('An error occurred. Please try again.', 'danger');
        return null;
    }
}

// ==================== AUTHENTICATION ==================== 

// Login function
async function login() {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    if (!username || !password) {
        showAlert('Please enter username and password', 'danger');
        return;
    }

    const response = await apiRequest('/auth/login', 'POST', { username, password });
    if (response && response.status === 200) {
        localStorage.setItem('user', JSON.stringify(response.data));
        showAlert('Login successful! Redirecting...', 'success');
        setTimeout(() => {
            window.location.href = '/dashboard.php';
        }, 1000);
    } else if (response && response.message) {
        showAlert(response.message, 'danger');
    }
}

// Logout function
async function logout() {
    await apiRequest('/auth/logout', 'POST');
    localStorage.removeItem('user');
    window.location.href = '/index.php';
}

// Check if user is logged in
function checkAuth() {
    const user = localStorage.getItem('user');
    if (!user) {
        window.location.href = '/index.php';
        return null;
    }
    return JSON.parse(user);
}

// Load user info
function loadUserInfo() {
    const user = checkAuth();
    if (user) {
        document.getElementById('user-name').textContent = user.username;
    }
}

// ==================== EMPLOYEES ==================== 

// Load all employees
async function loadEmployees() {
    showLoading(true);
    const response = await apiRequest('/employees', 'GET');
    showLoading(false);

    if (response && response.status === 200) {
        displayEmployees(response.data);
    }
}

// Display employees in table
function displayEmployees(employees) {
    const tbody = document.querySelector('#employees-table tbody');
    tbody.innerHTML = '';

    if (employees.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No employees found</td></tr>';
        return;
    }

    employees.forEach(emp => {
        const row = `
            <tr>
                <td>${emp.id}</td>
                <td>${emp.name}</td>
                <td>${emp.email}</td>
                <td>${emp.department_name || 'N/A'}</td>
                <td class="action-buttons">
                    <button class="btn-sm btn-edit" onclick="editEmployee(${emp.id})">Edit</button>
                    <button class="btn-sm btn-delete" onclick="deleteEmployee(${emp.id})">Delete</button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

// Open add/edit employee modal
function openEmployeeModal(id = null) {
    const modal = document.getElementById('employee-modal');
    const form = document.getElementById('employee-form');
    
    if (id) {
        document.querySelector('.modal-header h2').textContent = 'Edit Employee';
        loadEmployeeData(id);
    } else {
        document.querySelector('.modal-header h2').textContent = 'Add New Employee';
        form.reset();
    }
    
    modal.classList.add('show');
}

// Load employee data for editing
async function loadEmployeeData(id) {
    const response = await apiRequest(`/employees/${id}`, 'GET');
    if (response && response.status === 200) {
        const emp = response.data;
        document.getElementById('emp-id').value = emp.id;
        document.getElementById('emp-name').value = emp.name;
        document.getElementById('emp-email').value = emp.email;
        document.getElementById('emp-dept').value = emp.department_id || '';
        document.getElementById('emp-salary').value = emp.salary || '';
    }
}

// Save employee
async function saveEmployee() {
    const id = document.getElementById('emp-id').value;
    const name = document.getElementById('emp-name').value;
    const email = document.getElementById('emp-email').value;
    const dept = document.getElementById('emp-dept').value;
    const salary = document.getElementById('emp-salary').value;

    if (!name || !email) {
        showAlert('Name and email are required', 'danger');
        return;
    }

    const data = { name, email, department_id: dept, salary };
    const method = id ? 'PUT' : 'POST';
    const endpoint = id ? `/employees/${id}` : '/employees';

    const response = await apiRequest(endpoint, method, data);
    if (response && response.status >= 200 && response.status < 300) {
        showAlert(response.message, 'success');
        closeModal('employee-modal');
        loadEmployees();
    }
}

// Edit employee
function editEmployee(id) {
    openEmployeeModal(id);
}

// Delete employee
async function deleteEmployee(id) {
    if (confirm('Are you sure you want to delete this employee?')) {
        const response = await apiRequest(`/employees/${id}`, 'DELETE');
        if (response && response.status === 200) {
            showAlert(response.message, 'success');
            loadEmployees();
        }
    }
}

// ==================== PROJECTS ==================== 

// Load all projects
async function loadProjects() {
    showLoading(true);
    const response = await apiRequest('/projects', 'GET');
    showLoading(false);

    if (response && response.status === 200) {
        displayProjects(response.data);
    }
}

// Display projects in table
function displayProjects(projects) {
    const tbody = document.querySelector('#projects-table tbody');
    tbody.innerHTML = '';

    if (projects.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No projects found</td></tr>';
        return;
    }

    projects.forEach(proj => {
        const statusBadge = `<span class="badge badge-${getStatusClass(proj.status)}">${proj.status}</span>`;
        const row = `
            <tr>
                <td>${proj.id}</td>
                <td>${proj.title}</td>
                <td>${proj.start_date || 'N/A'}</td>
                <td>${statusBadge}</td>
                <td class="action-buttons">
                    <button class="btn-sm btn-edit" onclick="editProject(${proj.id})">Edit</button>
                    <button class="btn-sm btn-delete" onclick="deleteProject(${proj.id})">Delete</button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

// Get status badge class
function getStatusClass(status) {
    switch (status) {
        case 'Active':
            return 'success';
        case 'Completed':
            return 'info';
        case 'On Hold':
            return 'warning';
        default:
            return 'danger';
    }
}

// Open add/edit project modal
function openProjectModal(id = null) {
    const modal = document.getElementById('project-modal');
    const form = document.getElementById('project-form');
    
    if (id) {
        document.querySelector('#project-modal .modal-header h2').textContent = 'Edit Project';
        loadProjectData(id);
    } else {
        document.querySelector('#project-modal .modal-header h2').textContent = 'Add New Project';
        form.reset();
    }
    
    modal.classList.add('show');
}

// Load project data for editing
async function loadProjectData(id) {
    const response = await apiRequest(`/projects/${id}`, 'GET');
    if (response && response.status === 200) {
        const proj = response.data;
        document.getElementById('proj-id').value = proj.id;
        document.getElementById('proj-title').value = proj.title;
        document.getElementById('proj-desc').value = proj.description;
        document.getElementById('proj-date').value = proj.start_date;
        document.getElementById('proj-status').value = proj.status;
    }
}

// Save project
async function saveProject() {
    const id = document.getElementById('proj-id').value;
    const title = document.getElementById('proj-title').value;
    const desc = document.getElementById('proj-desc').value;
    const date = document.getElementById('proj-date').value;
    const status = document.getElementById('proj-status').value;

    if (!title) {
        showAlert('Project title is required', 'danger');
        return;
    }

    const data = { title, description: desc, start_date: date, status };
    const method = id ? 'PUT' : 'POST';
    const endpoint = id ? `/projects/${id}` : '/projects';

    const response = await apiRequest(endpoint, method, data);
    if (response && response.status >= 200 && response.status < 300) {
        showAlert(response.message, 'success');
        closeModal('project-modal');
        loadProjects();
    }
}

// Edit project
function editProject(id) {
    openProjectModal(id);
}

// Delete project
async function deleteProject(id) {
    if (confirm('Are you sure you want to delete this project?')) {
        const response = await apiRequest(`/projects/${id}`, 'DELETE');
        if (response && response.status === 200) {
            showAlert(response.message, 'success');
            loadProjects();
        }
    }
}

// ==================== DASHBOARD ==================== 

// Load dashboard stats
async function loadDashboardStats() {
    const empStats = await apiRequest('/employees/stats', 'GET');
    const projStats = await apiRequest('/projects/stats', 'GET');

    if (empStats && empStats.status === 200) {
        document.getElementById('total-employees').textContent = empStats.data.total_employees;
    }

    if (projStats && projStats.status === 200) {
        document.getElementById('total-projects').textContent = projStats.data.total_projects;
    }
}

// ==================== UTILITY FUNCTIONS ==================== 

// Show alert message
function showAlert(message, type = 'info') {
    const alert = document.getElementById('alert');
    alert.textContent = message;
    alert.className = `alert alert-${type} show`;

    setTimeout(() => {
        alert.classList.remove('show');
    }, 3000);
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// Show/hide loading spinner
function showLoading(show) {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.style.display = show ? 'block' : 'none';
    }
}

// Search in table
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const filter = input.value.toUpperCase();

    for (let row of rows) {
        const text = row.textContent.toUpperCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    }
}

// Close modal when clicking outside
window.onclick = function (event) {
    const loginModal = document.getElementById('employee-modal');
    const projModal = document.getElementById('project-modal');

    if (event.target === loginModal) {
        loginModal.classList.remove('show');
    }
    if (event.target === projModal) {
        projModal.classList.remove('show');
    }
};

// Enter key support for forms
document.addEventListener('keypress', function (event) {
    if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') {
        const btn = event.target.closest('.modal-content')?.querySelector('.btn-primary');
        if (btn) btn.click();
    }
});
