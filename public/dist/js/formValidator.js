// formValidator.js
export class FormValidator {
	constructor(config) {
		this.form = config.form;
		
		this.matric = config.matric || null;
		this.email = config.email || null;
		this.password = config.password || null;
		this.confirmPassword = config.confirmPassword || null;
		
		this.submitBtn = config.submitBtn;
		
		this.matricError = config.matricError || null;
		this.emailError = config.emailError || null;
		this.passwordError = config.passwordError || null;
		this.confirmPasswordError = config.confirmPasswordError || null;
		
		this.passwordRegex = config.passwordRegex || null;
		
		this.matricRegex = config.matricRegex || /^(2[2-6])\d{10}$/;
		this.emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		
		this.init();
	}
	
	init() {
		this.submitBtn.disabled = true;
		
		if (this.password) this.password.disabled = true;
		if (this.confirmPassword) this.confirmPassword.disabled = true;
		
		this.setupInput(this.matric);
		this.setupInput(this.email);
		this.setupInput(this.password);
		this.setupInput(this.confirmPassword);
		
		this.setupCursor(this.submitBtn, this.submitBtn);
		
		this.form.addEventListener('submit', (e) => this.handleSubmit(e));
	}
	
	setupInput(input) {
		if (!input) return;
		
		const container = input.closest('div');
		
		input.addEventListener('focus', () => container.classList.add('focus'));
		input.addEventListener('blur', () => container.classList.remove('focus'));
		
		this.setupCursor(input, container);
		
		if (input === this.matric) {
			input.addEventListener('input', () => this.validateMatric());
		} else if (input === this.email) {
			input.addEventListener('input', () => this.validateEmail());
		} else if (input === this.password) {
			input.addEventListener('input', () => this.validatePassword());
		} else if (input === this.confirmPassword) {
			input.addEventListener('input', () => this.validateConfirmPassword());
		}
		
		input.addEventListener('keydown', (e) => {
			if (e.key === 'Enter') {
				e.preventDefault();
				
				if (input === this.matric) {
					this.validateMatric();
					if (this.email && !this.email.disabled) {
						this.email.focus();
					} else {
						if(!this.submitBtn.disabled) {
							this.form.requestSubmit();
						}
					}
				} else if (input === this.email) {
					this.validateEmail();
					if (this.password && !this.password.disabled) {
						this.password.focus();
					}
				} else if (input === this.password) {
					this.validatePassword();
					if (this.confirmPassword && !this.confirmPassword.disabled) {
						this.confirmPassword.focus();
					}
				} else if (input === this.confirmPassword) {
					this.validateConfirmPassword();
					if (!this.submitBtn.disabled) {
						this.form.requestSubmit();
					}
				}
			}
		});
	}
	
	setupCursor(input, container) {
		container.addEventListener('mouseenter', () => {
			if (input.disabled) {
				container.style.cursor = 'not-allowed';
			} else if (input.tagName.toLowerCase() === 'button') {
				container.style.cursor = 'pointer';
			} else {
				container.style.cursor = 'text';
			}
		});
		
		container.addEventListener('mouseleave', () => {
			container.style.cursor = '';
		});
	}
	
	// Matric No Validation
	validateMatric() {
		const container = this.matric.closest('div');
		
		if (this.matricRegex.test(this.matric.value.trim())) {
			if (this.matricError) this.matricError.textContent = '';
			this.setValid(container);
			
			this.submitBtn.disabled = false;
			
			if (this.email) {
				this.email.disabled = false;
			} else {
				this.submitBtn.style.display = "block";
				this.submitBtn.disabled = false;
			}
		} else {
			if (this.matricError) this.matricError.textContent = 'Invalid matric number.';
			this.setInvalid(container);
			
			this.submitBtn.disabled = true;
		}
	}
	
	validateEmail() {
		const container = this.email.closest('div');
		
		if (this.emailRegex.test(this.email.value.trim())) {
			if (this.emailError) this.emailError.textContent = '';
			this.setValid(container);
			
			if (this.password) this.password.disabled = false;
		} else {
			if (this.emailError) this.emailError.textContent = 'Please enter a valid email.';
			this.setInvalid(container);
			
			if (this.password) {
				this.password.value = '';
				this.password.disabled = true;
			}
			if (this.confirmPassword) {
				this.confirmPassword.value = '';
				this.confirmPassword.disabled = true;
			}
			
			this.submitBtn.disabled = true;
		}
	}
	
	validatePassword() {
		const container = this.password.closest('div');
		
		if (this.email && !this.emailRegex.test(this.email.value.trim())) {
			this.validateEmail();
			this.email.focus();
			return;
		}
		
		if (!this.passwordRegex || this.passwordRegex.test(this.password.value)) {
			if (this.passwordError) this.passwordError.textContent = '';
			this.setValid(container);
			
			if (this.confirmPassword) {
				this.confirmPassword.disabled = false;
			} else {
				this.submitBtn.disabled = false;
			}
		} else {
			if (this.passwordError) this.passwordError.textContent = 'Invalid password.';
			this.setInvalid(container);
			
			if (this.confirmPassword) {
				this.confirmPassword.value = '';
				this.confirmPassword.disabled = true;
			}
			
			this.submitBtn.disabled = true;
		}
	}
	
	validateConfirmPassword() {
		const container = this.confirmPassword.closest('div');
		
		if (this.password.value === this.confirmPassword.value) {
			if (this.confirmPasswordError) this.confirmPasswordError.textContent = '';
			this.setValid(container);
			
			this.submitBtn.style.display = "block";
			this.submitBtn.disabled = false;
		} else {
			if (this.confirmPasswordError) this.confirmPasswordError.textContent = 'Passwords do not match.';
			this.setInvalid(container);
			
			this.submitBtn.disabled = true;
		}
	}
	
	handleSubmit(e) {
		if (this.matric && !this.matricRegex.test(this.matric.value.trim())) {
			e.preventDefault();
			this.validateMatric();
			this.matric.focus();
			return;
		}
		
		if (this.email && !this.emailRegex.test(this.email.value.trim())) {
			e.preventDefault();
			this.validateEmail();
			this.email.focus();
			return;
		}
		
		if (this.password && this.passwordRegex && !this.passwordRegex.test(this.password.value)) {
			e.preventDefault();
			this.validatePassword();
			this.password.focus();
			return;
		}
		
		if (this.confirmPassword && this.password.value !== this.confirmPassword.value) {
			e.preventDefault();
			this.validateConfirmPassword();
			this.confirmPassword.focus();
			return;
		}
		
		alert('Form submitted successfully!');
		this.form.submit();
	}
	
	// Updating UI feedback
	setValid(container) {
		container.style.border = "2px solid blue";
	}
	
	setInvalid(container) {
		container.style.border = "2px solid red";
	}
	
}


// Password toggle (unchanged)
export function setupPasswordToggle(input, toggleIcon) {
	if (!input || !toggleIcon) {
		console.warn('Password toggle skipped: missing element', { input, toggleIcon });
		return;
	}
	
	toggleIcon.addEventListener('click', () => {
		const isHidden = input.type === 'password';
		input.type = isHidden ? 'text' : 'password';
		toggleIcon.classList.toggle('ph-eye-closed');
		toggleIcon.classList.toggle('ph-eye');
	});
}
