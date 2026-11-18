CREATE TABLE documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'General',
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    processing_min_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    processing_max_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    release_methods VARCHAR(80) NOT NULL DEFAULT 'Hardcopy',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documents_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_requirements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_document_requirements_document_description (document_id, description),
    CONSTRAINT fk_document_requirements_document
        FOREIGN KEY (document_id) REFERENCES documents (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE release_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_release_methods_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_release_methods (
    document_id BIGINT UNSIGNED NOT NULL,
    release_method_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (document_id, release_method_id),
    CONSTRAINT fk_document_release_methods_document
        FOREIGN KEY (document_id) REFERENCES documents (id),
    CONSTRAINT fk_document_release_methods_method
        FOREIGN KEY (release_method_id) REFERENCES release_methods (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
