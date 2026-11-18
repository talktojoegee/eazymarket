<option value="">{{ __('labels.default_font') }}</option>
<option value="Inter">Inter</option>
<option value="Roboto">Roboto</option>
<option value="Open Sans">Open Sans</option>
<option value="Lato">Lato</option>
<option value="DM Sans">DM Sans</option>
<option value="Manrope">Manrope</option>
<option value="Nunito Sans">Nunito Sans</option>
<option value="Montserrat">Montserrat</option>
<option value="Poppins">Poppins</option>
<option value="Raleway">Raleway</option>
<option value="Rubik">Rubik</option>
<option value="Work Sans">Work Sans</option>
<option value="Source Sans 3">Source Sans 3</option>
<option value="IBM Plex Sans">IBM Plex Sans</option>
<option value="Fira Sans">Fira Sans</option>
<option value="Ubuntu">Ubuntu</option>
<option value="Merriweather">Merriweather</option>
<option value="Lora">Lora</option>
<option value="Playfair Display">Playfair Display</option>
<option value="Noto Sans">Noto Sans</option>
<option value="Noto Serif">Noto Serif</option>
<option value="Noto Sans Devanagari">Noto Sans Devanagari</option>
<option value="Noto Sans Arabic">Noto Sans Arabic</option>

@once
    @push('script')
        <script src="{{ hyperAsset('assets/js/google-font-picker.js') }}" defer></script>
    @endpush
@endonce
