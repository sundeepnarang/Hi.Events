echo "Run the following command to restore the database:"
echo "cat [BACKUP_FILE_PATH] | docker compose exec -T postgres psql -U [DB_USER] -d [DB_NAME]"
echo ""
echo "Example: cat sos-hievents_backup_[DATE].sql | docker compose exec -T postgres psql -U postgres -d sos-events-db"