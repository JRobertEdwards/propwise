#!/usr/bin/env bash
# One-time droplet setup for Propwise.
# Run this on a fresh Ubuntu 24.04 droplet as root, then switch to the deploy user.
# Recommended droplet: Basic $12/month, 2 GB RAM, 1 vCPU, 50 GB SSD, Ubuntu 24.04 LTS.
set -e

DEPLOY_USER="deploy"
DEPLOY_PATH="/var/www/propwise"
REPO_URL="git@github.com:YOUR_USERNAME/propwise.git"  # update this

# --- 1. System packages ---
apt-get update && apt-get upgrade -y
apt-get install -y git curl ufw fail2ban

# --- 2. Docker ---
curl -fsSL https://get.docker.com | sh
usermod -aG docker "$DEPLOY_USER" || true  # run after creating user if needed

# --- 3. Create deploy user ---
if ! id "$DEPLOY_USER" &>/dev/null; then
    adduser --disabled-password --gecos "" "$DEPLOY_USER"
    usermod -aG docker "$DEPLOY_USER"
fi

# --- 4. Firewall ---
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# --- 5. SSH deploy key ---
# On your local machine, generate a key pair:
#   ssh-keygen -t ed25519 -C "propwise-deploy" -f ~/.ssh/propwise_deploy
# Add the PUBLIC key (~/.ssh/propwise_deploy.pub) as a Deploy Key in GitHub:
#   GitHub repo → Settings → Deploy keys → Add deploy key (read-only)
# Add the PRIVATE key as a GitHub Actions secret (DEPLOY_SSH_KEY).
# On the droplet, add the server's own key so git can pull from GitHub:
su - "$DEPLOY_USER" -c "
    mkdir -p ~/.ssh && chmod 700 ~/.ssh
    ssh-keyscan github.com >> ~/.ssh/known_hosts
"
echo ""
echo ">>> Generate a deploy key on the server as $DEPLOY_USER, or copy one in:"
echo "    su - $DEPLOY_USER"
echo "    ssh-keygen -t ed25519 -C 'propwise-deploy-droplet' -f ~/.ssh/id_ed25519"
echo "    cat ~/.ssh/id_ed25519.pub   # add this to GitHub Deploy Keys"

# --- 6. Clone repo ---
mkdir -p "$DEPLOY_PATH"
chown "$DEPLOY_USER:$DEPLOY_USER" "$DEPLOY_PATH"
su - "$DEPLOY_USER" -c "git clone $REPO_URL $DEPLOY_PATH"

# --- 7. Create .env ---
echo ""
echo ">>> Copy and populate the production env:"
echo "    cp $DEPLOY_PATH/.env.production.example $DEPLOY_PATH/.env"
echo "    nano $DEPLOY_PATH/.env"
echo ""
echo "    Set: APP_KEY (generate with: docker run --rm php:8.3-alpine php -r \"echo base64_encode(random_bytes(32));\")"
echo "    Set: DB_PASSWORD (choose a strong password)"
echo "    Set: APP_URL (your domain or http://<droplet-ip>)"

# --- 8. First boot ---
echo ""
echo ">>> When .env is ready, start the stack:"
echo "    cd $DEPLOY_PATH"
echo "    docker compose -f docker-compose.prod.yml up -d"
echo "    docker compose -f docker-compose.prod.yml exec app php artisan key:generate --force"
echo "    docker compose -f docker-compose.prod.yml exec app php artisan migrate --force"
echo "    docker compose -f docker-compose.prod.yml exec app php artisan storage:link"
echo "    docker compose -f docker-compose.prod.yml exec app php artisan optimize"

# --- 9. SSL (optional, requires a domain) ---
echo ""
echo ">>> For SSL with Let's Encrypt (requires a domain pointing to this droplet):"
echo "    apt-get install -y certbot python3-certbot-nginx"
echo "    # First: update docker/nginx/default.conf server_name to your domain"
echo "    certbot --nginx -d your-domain.com"
echo "    # Certbot will modify nginx.conf automatically. Restart nginx container after:"
echo "    docker compose -f docker-compose.prod.yml restart nginx"

# --- 10. GitHub Actions secrets ---
echo ""
echo ">>> Add these secrets to GitHub repo → Settings → Secrets → Actions:"
echo "    DEPLOY_HOST   = <droplet IP>"
echo "    DEPLOY_USER   = $DEPLOY_USER"
echo "    DEPLOY_SSH_KEY = <private SSH key that can SSH into the droplet as $DEPLOY_USER>"
echo "    DEPLOY_PATH   = $DEPLOY_PATH"
