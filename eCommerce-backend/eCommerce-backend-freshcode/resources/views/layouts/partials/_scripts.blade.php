<!-- include jQuery library -->
<script src="{{asset('assets/vendor/axios/axios.min.js')}}"></script>
<script src="{{asset('assets/vendor/jquery/jquery.js')}}"></script>

<script src="{{asset('assets/theme/js/tabler.min.js')}}" defer></script>
<!-- include FilePond library -->
<script src="{{asset('assets/vendor/filepond/js/filepond.min.js')}}"></script>

<!-- include FilePond plugins -->
<script src="{{asset('assets/vendor/filepond/js/filepond-plugin-image-preview.min.js')}}"></script>
<script src="{{asset('assets/vendor/filepond/js/filepond-plugin-file-validate-type.js')}}"></script>
<script src="{{asset('assets/vendor/filepond/js/filepond-plugin-file-validate-size.js')}}"></script>

<!-- include FilePond jQuery adapter -->
<script src="{{asset('assets/vendor/filepond/js/filepond.jquery.js')}}" defer></script>
<script src="{{hyperAsset('assets/js/filepond.custom.js')}}" defer></script>

<script src="{{asset('assets/vendor/lightbox/index.js')}}"></script>

<script src="{{asset('assets/vendor/tom-select/js/tom-select.base.min.js')}}" defer></script>
<script src="{{hyperAsset('assets/js/tom-select-pickers.js')}}" defer></script>

<script src="{{asset('assets/vendor/sweetalert/js/sweetalert2.all.min.js')}}"></script>
<script src="{{hyperAsset('assets/js/panel-toast.js')}}"></script>
<script>
    window.PanelToast.configure({
        labels: {
            notifications: @json(__('labels.notifications')),
            clearAll: @json(__('labels.clear_all_notifications')),
            showLess: @json(__('labels.show_less')),
            close: @json(__('labels.close')),
            view: @json(__('labels.view')),
        }
    });
</script>

<script src="{{asset('assets/vendor/datatable/js/dataTables.js')}}" defer></script>
<script src="{{asset('assets/vendor/datatable/js/dataTables.bootstrap5.js')}}" defer></script>
<script src="{{asset('assets/vendor/datatable/js/dataTables.buttons.js')}}" defer></script>
<script src="{{asset('assets/vendor/datatable/js/buttons.bootstrap5.js')}}" defer></script>
<script src="{{asset('assets/vendor/datatable/js/buttons.colVis.min.js')}}" defer></script>
<script src="{{asset('assets/vendor/datatable/js/buttons.html5.min.js')}}" defer></script>



<script src="{{asset('assets/vendor/hugerte/hugerte.min.js')}}" defer></script>

<script src="{{hyperAsset('assets/js/datatable.custom.js')}}" defer></script>
<script src="{{hyperAsset('assets/js/custom.js')}}" defer></script>
<script type="module" src="{{hyperAsset('assets/js/firebase.js')}}" defer></script>
<script src="{{hyperAsset('assets/admin/js/custom.js')}}" defer></script>
<script src="{{hyperAsset('assets/seller/js/custom.js')}}" defer></script>
<script src="{{hyperAsset('assets/js/sidebar.js')}}" defer></script>
<script src="{{hyperAsset('assets/js/global-search.js')}}" defer></script>
<script src="{{ asset('assets/vendor/driverjs/driver.js') }}"></script>
<script src="{{ hyperAsset('assets/js/tour-manager.js') }}"></script>
<script src="{{ hyperAsset('assets/js/tour-drawer.js') }}"></script>
@stack('scripts')
<script>
    const base_url = document.getElementById('base_url')?.value;
    const user_id = document.getElementById('user_id')?.value;
    const currencySymbol = document.getElementById('selected-currency-symbol')?.value;
    function getCurrentTheme() {
        const storedTheme = localStorage.getItem('tabler-theme');
        const dataTheme = document.documentElement.getAttribute('data-bs-theme');
        return storedTheme || dataTheme || 'light';
    }

    function setTheme(theme) {
        localStorage.setItem('tabler-theme', theme);

        if (theme === 'light') {
            document.documentElement.removeAttribute('data-bs-theme');
        } else {
            document.documentElement.setAttribute('data-bs-theme', theme);
        }

        updateThemeIcon(theme);
    }

    function updateThemeIcon(theme) {
        const themeIcon = document.getElementById('theme-icon');
        if (themeIcon) {
            if (theme === 'dark') {
                themeIcon.className = 'ti ti-sun fs-2';
            } else {
                themeIcon.className = 'ti ti-moon fs-2';
            }
        }
    }

    function toggleTheme() {
        const currentTheme = getCurrentTheme();
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const currentTheme = getCurrentTheme();
        updateThemeIcon(currentTheme);
    });
</script>
