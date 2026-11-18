(() => {
    const apiBase = 'https://psgc.gitlab.io/api';
    const responseCache = new Map();

    async function fetchLocations(url) {
        if (!responseCache.has(url)) {
            responseCache.set(url, fetch(url).then(response => {
                if (!response.ok) {
                    throw new Error(`Location service returned ${response.status}.`);
                }
                return response.json();
            }));
        }
        return responseCache.get(url);
    }

    function setOptions(select, items, placeholder) {
        select.replaceChildren(new Option(placeholder, ''));
        for (const item of items) {
            select.add(new Option(item.name, item.name, false, false));
            select.options[select.options.length - 1].dataset.code = item.code;
        }
        select.disabled = false;
    }

    document.querySelectorAll('[data-psgc-location]').forEach(async group => {
        const province = group.querySelector('[data-location="province"]');
        const city = group.querySelector('[data-location="city"]');
        const barangay = group.querySelector('[data-location="barangay"]');
        const status = group.querySelector('[data-location-status]');
        if (!province || !city || !barangay || !status) {
            return;
        }

        const reportError = (error, provinceLoadFailed = false) => {
            status.textContent = `Location options could not be loaded. Check your connection and try again. (${error.message})`;
            status.hidden = false;
            province.disabled = provinceLoadFailed;
            city.disabled = true;
            barangay.disabled = true;
        };
        const resetChildren = () => {
            setOptions(city, [], 'Select city/municipality');
            city.disabled = true;
            setOptions(barangay, [], 'Select barangay');
            barangay.disabled = true;
        };

        province.disabled = true;
        city.disabled = true;
        barangay.disabled = true;
        try {
            const provinces = await fetchLocations(`${apiBase}/provinces/`);
            provinces.push({ code: '130000000', name: 'Metro Manila (NCR)' });
            setOptions(province, provinces.sort((a, b) => a.name.localeCompare(b.name)), 'Select province');
            resetChildren();
            status.hidden = true;

            province.addEventListener('change', async () => {
                resetChildren();
                if (!province.value) {
                    return;
                }
                status.textContent = 'Loading cities/municipalities…';
                status.hidden = false;
                const code = province.options[province.selectedIndex].dataset.code;
                const route = code === '130000000' ? 'regions' : 'provinces';
                try {
                    const cities = await fetchLocations(`${apiBase}/${route}/${code}/cities-municipalities/`);
                    setOptions(city, cities.sort((a, b) => a.name.localeCompare(b.name)), 'Select city/municipality');
                    status.hidden = true;
                } catch (error) {
                    reportError(error);
                }
            });

            city.addEventListener('change', async () => {
                setOptions(barangay, [], 'Select barangay');
                barangay.disabled = true;
                if (!city.value) {
                    return;
                }
                status.textContent = 'Loading barangays…';
                status.hidden = false;
                const code = city.options[city.selectedIndex].dataset.code;
                try {
                    const barangays = await fetchLocations(`${apiBase}/cities-municipalities/${code}/barangays/`);
                    setOptions(barangay, barangays.sort((a, b) => a.name.localeCompare(b.name)), 'Select barangay');
                    status.hidden = true;
                } catch (error) {
                    reportError(error, true);
                }
            });
        } catch (error) {
            reportError(error);
        }
    });
})();
