# Dockerfile
FROM php:8.3-cli

# Build arguments
ARG UID=1000
ARG GID=1000
ARG USER=appuser

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip zip libzip-dev openssh-client sudo && \
    docker-php-ext-install zip && \
    rm -rf /var/lib/apt/lists/*

# Install Composer globally
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create user with same UID/GID as host
RUN groupadd -g ${GID} ${USER} && \
    useradd -u ${UID} -g ${GID} -m -s /bin/bash ${USER} && \
    echo "${USER} ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers

# Configure git to trust the /app directory
RUN git config --global --add safe.directory /app

# Create necessary directories for user
RUN mkdir -p /home/${USER}/.ssh /home/${USER}/.composer && \
    ssh-keyscan github.com >> /home/${USER}/.ssh/known_hosts && \
    chown -R ${USER}:${USER} /home/${USER} && \
    chmod 700 /home/${USER}/.ssh

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Configure GitHub token if provided
ARG GITHUB_TOKEN
RUN if [ -n "$GITHUB_TOKEN" ]; then \
        composer config --global github-oauth.github.com $GITHUB_TOKEN; \
    fi

# Default command is overridden in docker-compose.yml
CMD ["tail", "-f", "/dev/null"]
