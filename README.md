# Military Inventory System

A comprehensive PHP/SQL-based inventory management system designed specifically for military assets including vehicles, ammunition, and personnel.

## Features

- **Dashboard**: Real-time visualization of inventory status with color-coded categories
- **Vehicle Management**: Track combat, transport, and support vehicles
- **Ammunition Inventory**: Manage different ammunition types with expiration tracking
- **Personnel Records**: Maintain complete military personnel information
- **Maintenance Logs**: Schedule and track vehicle maintenance
- **Transaction History**: Complete audit trail of all inventory movements
- **Responsive Design**: Modern admin interface that works on mobile and desktop

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache or Nginx web server
- Modern web browser

## Installation

1. Clone or download this repository
2. Import `database/military_inventory.sql` into your MySQL server
3. Update the database configuration in `includes/config.php`
4. Access the system through your web browser

## Update Existing Installation

If you're updating an existing installation, run the database update script:

```
http://your-server/MilinS/database/update_schema.php
```

This will apply any necessary schema changes to your existing database.

## Default Login

- Username: admin
- Password: admin123

*Remember to change the default password after logging in for the first time.*

## Usage

1. **Dashboard**: Monitor inventory status at a glance with real-time metrics
2. **Vehicles**: Add, edit, or remove military vehicles with detailed specifications
3. **Ammunition**: Track ammunition inventory with expiration monitoring
4. **Personnel**: Manage military personnel records and assignments
5. **Reports**: Generate detailed reports for inventory planning and auditing

## Security Notes

This system is designed for secure internal military use. Please ensure:

1. Server is protected by appropriate firewall settings
2. HTTPS is enabled for encrypted communication
3. Regular database backups are performed
4. User access is strictly controlled
5. Default credentials are changed immediately after installation

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Screenshots

![Dashboard](assets/img/screenshots/dashboard.png)
![Vehicle Management](assets/img/screenshots/vehicles.png)
![Ammunition Inventory](assets/img/screenshots/ammunition.png) 