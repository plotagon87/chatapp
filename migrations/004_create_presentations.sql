-- Presentations feature tables

CREATE TABLE presentations (
    presentation_id INT PRIMARY KEY AUTO_INCREMENT,
    presenter_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    current_slide INT DEFAULT 1,
    allow_download BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (presenter_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE presentation_files (
    file_id INT PRIMARY KEY AUTO_INCREMENT,
    presentation_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    slide_number INT NOT NULL DEFAULT 1,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (presentation_id) REFERENCES presentations(presentation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE presentation_viewers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    presentation_id INT NOT NULL,
    user_id INT NOT NULL,
    approved BOOLEAN DEFAULT FALSE,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (presentation_id) REFERENCES presentations(presentation_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_viewer (presentation_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE presentation_announcements (
    announcement_id INT PRIMARY KEY AUTO_INCREMENT,
    presentation_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (presentation_id) REFERENCES presentations(presentation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
