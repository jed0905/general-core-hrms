/**
 * Input cleansing and sanitization helper functions
 * Used to clean user input before validation and submission
 */

/**
 * Clean and sanitize general text input
 * @param {string} input - The input string to clean
 * @param {Object} options - Cleaning options
 * @returns {string} - Cleaned string
 */
export const cleanTextInput = (input, options = {}) => {
  if (!input || typeof input !== 'string') {
    return input;
  }

  const {
    trim = true,
    removeExtraSpaces = true,
    removeSpecialChars = false,
    allowedSpecialChars = '',
    maxLength = null,
    convertCase = null, // 'upper', 'lower', 'title', 'sentence'
    removeEmojis = true,
    removeHtml = true,
  } = options;

  let cleaned = input;

  // Remove HTML tags and entities
  if (removeHtml) {
    cleaned = cleaned.replace(/<[^>]*>/g, '');
    cleaned = cleaned.replace(/&[^;]+;/g, '');
  }

  // Remove emojis and other Unicode symbols
  if (removeEmojis) {
    cleaned = cleaned.replace(/[\u{1F600}-\u{1F64F}]|[\u{1F300}-\u{1F5FF}]|[\u{1F680}-\u{1F6FF}]|[\u{1F1E0}-\u{1F1FF}]|[\u{2600}-\u{26FF}]|[\u{2700}-\u{27BF}]/gu, '');
  }

  // Trim whitespace
  if (trim) {
    cleaned = cleaned.trim();
  }

  // Remove extra spaces
  if (removeExtraSpaces) {
    cleaned = cleaned.replace(/\s+/g, ' ');
  }

  // Remove special characters (keep only alphanumeric, spaces, and allowed special chars)
  // if (removeSpecialChars) {
  //   const allowedPattern = allowedSpecialChars ? `\\s${allowedSpecialChars.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}` : '\\s';
  //   const regex = new RegExp(`[^a-zA-Z0-9${allowedPattern}]`, 'g');
  //   cleaned = cleaned.replace(regex, '');
  // }

  if (removeSpecialChars) {
    // Keep everything except spaces
    cleaned = cleaned.replace(/\s/g, '');
  }

  // Apply case conversion
  if (convertCase) {
    switch (convertCase) {
      case 'upper':
        cleaned = cleaned.toUpperCase();
        break;
      case 'lower':
        cleaned = cleaned.toLowerCase();
        break;
      case 'title':
        cleaned = cleaned.replace(/\w\S*/g, (txt) =>
          txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
        );
        break;
      case 'sentence':
        cleaned = cleaned.charAt(0).toUpperCase() + cleaned.slice(1).toLowerCase();
        break;
    }
  }

  // Limit length
  if (maxLength && cleaned.length > maxLength) {
    cleaned = cleaned.substring(0, maxLength);
  }

  return cleaned;
};

/**
 * Clean username input
 * @param {string} username - The username to clean
 * @returns {string} - Cleaned username
 */
export const cleanUsername = (username) => {
  if (!username) return username;

  return cleanTextInput(username, {
    trim: true,
    removeExtraSpaces: true,
    removeSpecialChars: true,
    allowedSpecialChars: '._-',
    maxLength: 50,
    convertCase: 'lower',
    removeEmojis: true,
    removeHtml: true,
  });
};

/**
 * Clean password input (minimal cleaning to preserve security)
 * @param {string} password - The password to clean
 * @returns {string} - Cleaned password
 */
export const cleanPassword = (password) => {
  if (!password) return password;

  // Only remove leading/trailing whitespace to preserve password integrity
  return password.trim();
};

/**
 * Clean email input
 * @param {string} email - The email to clean
 * @returns {string} - Cleaned email
 */
export const cleanEmail = (email) => {
  if (!email) return email;

  return cleanTextInput(email, {
    trim: true,
    removeExtraSpaces: true,
    convertCase: 'lower',
    removeEmojis: true,
    removeHtml: true,
  });
};

/**
 * Clean name input (first name, last name, etc.)
 * @param {string} name - The name to clean
 * @returns {string} - Cleaned name
 */
export const cleanName = (name) => {
  if (!name) return name;

  return cleanTextInput(name, {
    trim: true,
    removeExtraSpaces: true,
    removeSpecialChars: true,
    allowedSpecialChars: "'-.",
    maxLength: 50,
    convertCase: 'title',
    removeEmojis: true,
    removeHtml: true,
  });
};

/**
 * Clean phone number input
 * @param {string} phone - The phone number to clean
 * @returns {string} - Cleaned phone number
 */
export const cleanPhoneNumber = (phone) => {
  if (!phone) return phone;

  return cleanTextInput(phone, {
    trim: true,
    removeExtraSpaces: true,
    removeSpecialChars: true,
    allowedSpecialChars: '+()-.',
    maxLength: 20,
    removeEmojis: true,
    removeHtml: true,
  });
};

/**
 * Sanitize object properties recursively
 * @param {Object} obj - Object to sanitize
 * @param {Object} fieldRules - Rules for each field
 * @returns {Object} - Sanitized object
 */
export const sanitizeFormData = (obj, fieldRules = {}) => {
  if (!obj || typeof obj !== 'object') return obj;

  const sanitized = { ...obj };

  Object.keys(sanitized).forEach(key => {
    const value = sanitized[key];
    const rules = fieldRules[key];

    if (typeof value === 'string' && rules) {
      if (rules.type === 'username') {
        sanitized[key] = cleanUsername(value);
      } else if (rules.type === 'password') {
        sanitized[key] = cleanPassword(value);
      } else if (rules.type === 'email') {
        sanitized[key] = cleanEmail(value);
      } else if (rules.type === 'name') {
        sanitized[key] = cleanName(value);
      } else if (rules.type === 'phone') {
        sanitized[key] = cleanPhoneNumber(value);
      } else if (rules.type === 'text') {
        sanitized[key] = cleanTextInput(value, rules.options || {});
      }
    } else if (typeof value === 'string' && !rules) {
      // Default cleaning for unspecified string fields
      sanitized[key] = cleanTextInput(value, {
        trim: true,
        removeExtraSpaces: true,
        removeEmojis: true,
        removeHtml: true,
      });
    }
  });

  return sanitized;
};

/**
 * Validate cleaned input against common patterns
 * @param {string} input - Input to validate
 * @param {string} type - Type of validation
 * @returns {boolean} - Validation result
 */
export const validateCleanedInput = (input, type) => {
  if (!input) return false;

  const patterns = {
    username: /^[a-z0-9._-]{3,20}$/,
    email: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/,
    name: /^[a-zA-Z\s'-\.]{1,50}$/,
    phone: /^[\d\s\+\(\)\-\.]{7,20}$/,
  };

  return patterns[type] ? patterns[type].test(input) : true;
};

/**
 * Get field-specific cleaning rules for user form
 * @returns {Object} - Field rules object
 */
export const getUserFormCleaningRules = () => {
  return {
    username: { type: 'username' },
    password: { type: 'password' },
    password_confirmation: { type: 'password' },
    email: { type: 'email' },
    firstName: { type: 'name' },
    lastName: { type: 'name' },
    middleName: { type: 'name' },
  };
};
