            </div> <!-- End page-container -->
        </div> <!-- End content -->
    </div> <!-- End wrapper -->
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.0/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/vfs_fonts.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar Toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
            });
            
            // Digital clock with seconds
            const clockDisplay = document.getElementById('digitalClock');
            let use24HourFormat = true; // Default to 24-hour format
            
            function updateClock() {
                const now = new Date();
                let hours = now.getHours();
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                
                if (use24HourFormat) {
                    // 24-hour format
                    clockDisplay.textContent = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds}`;
                } else {
                    // 12-hour format with AM/PM
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12; // Convert 0 to 12 for 12 AM
                    clockDisplay.textContent = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
                }
            }
            
            // Toggle time format when clock is clicked
            clockDisplay.addEventListener('click', function() {
                use24HourFormat = !use24HourFormat;
                updateClock(); // Update immediately to show new format
                
                // Add a brief animation effect
                this.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 200);
            });
            
            // Initial call to display time immediately
            updateClock();
            
            // Update clock every second
            setInterval(updateClock, 1000);
            
            // Dismiss alerts automatically after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) {
                        closeBtn.click();
                    }
                }, 5000);
            });
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Initialize popovers
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl)
            });
            
            // Responsive adjustments
            function handleResize() {
                if (window.innerWidth < 768) {
                    sidebar.classList.add('collapsed');
                    content.classList.add('expanded');
                } else {
                    sidebar.classList.remove('collapsed');
                    content.classList.remove('expanded');
                }
            }
            
            // Initial check
            handleResize();
            
            // Listen for window resize
            window.addEventListener('resize', handleResize);
        });
    </script>
</body>
</html> 