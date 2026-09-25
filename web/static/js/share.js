/* global $, jQuery, Choices, localization, plotDataChoices, initSlider, updCharts,
   resizeSplitter, initMapLeaflet, extractValidSegmentsWithIndices, SHARE_CONFIG */

sid = SHARE_CONFIG.sessionId;
uid = SHARE_CONFIG.uid;
sig = SHARE_CONFIG.sig;

let streamBtn_svg = null;
let stream = false;

// --------------------------------------------------------------
// Plot data + language switch (was: first inline <script>)
// --------------------------------------------------------------
$(document).ready(function () {

    // Lang switch works regardless of plot_data presence
    const langSwitch   = document.getElementById('lang-switch');
    const selectedLang = document.getElementById('selected-lang');
    const langOptions  = document.getElementById('lang-options');

    if (langSwitch && selectedLang && langOptions) {
        function closeDropdown() {
            langOptions.classList.remove('show');
        }

        selectedLang.addEventListener('click', function (event) {
            event.stopPropagation();
            if (langOptions.classList.contains('show')) {
                closeDropdown();
            } else {
                langOptions.classList.add('show');
            }
        });

        langOptions.querySelectorAll('li').forEach(option => {
            option.addEventListener('click', function () {
                const selectedValue = this.getAttribute('data-value');
                closeDropdown();

                fetch(`translations.php?lang=${selectedValue}`)
                    .then(() => {
                        localization.setLang(selectedValue);
                        location.reload();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            });
        });

        document.addEventListener('click', function (event) {
            if (!langSwitch.contains(event.target)) {
                closeDropdown();
            }
        });
    }

    if (!document.getElementById('plot_data')) return;

    let plotData = $('#plot_data');
    let lastValue = plotData.val() || [];

    let debounceTimer;
    function handleChange() {
        const newValue = plotDataChoices.getValue(true) || [];
        if (JSON.stringify(newValue) !== JSON.stringify(lastValue)) {
            lastValue = newValue;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                updCharts();
            }, 1500);
        }
    }

    const observer = new MutationObserver((mutations) => {
        if (!lastValue.length && $('#placeholder')[0] != undefined) {
            updCharts();
        }
    });

    const targetNode = $('#right-container')[0];

    if (targetNode) {
        observer.observe(targetNode, {childList: true, subtree: true});
    }

    plotDataChoices = new Choices('#plot_data', {
        removeItemButton: true,
        placeholder: true,
        shouldSort: false,
        itemSelectText: null,
        maxItemCount: 10,
        maxItemText: (maxItemCount) => {
            return `${localization.key['overdata']} ${maxItemCount}`;
        },
        noResultsText: localization.key['vars.nores'] || 'Oops, nothing found!',
        placeholderValue: localization.key['vars.placeholder'] || 'Choose data...',
        classNames: {
            containerInner: ['choices__inner', 'choices__inner__plot'],
        },
    });

    plotData.on('change', handleChange);
    updCharts();
    $(".copyright").html(`&copy; 2019-${(new Date).getFullYear()} RedBox Automotive`);
    resizeSplitter();
});

// --------------------------------------------------------------
// Slider init (was: inline <script> before .slider-container)
// --------------------------------------------------------------
window.jsTimeMap = SHARE_CONFIG.itime
    ? String(SHARE_CONFIG.itime).split(',').map(Number).filter(n => !isNaN(n)).reverse()
    : [];

if (window.jsTimeMap.length && typeof initSlider === 'function') {
    initSlider(window.jsTimeMap, window.jsTimeMap[0], window.jsTimeMap.at(-1));
}

// --------------------------------------------------------------
// Map init (was: last inline <script> with translations-cache wait)
// --------------------------------------------------------------
window.rawPath = SHARE_CONFIG.imapdata
    ? JSON.parse('[' + SHARE_CONFIG.imapdata + ']')
    : [];

if (!window.rawPath.length) {
    $('#map-div').hide();
} else {
    const coordsOnly = window.rawPath.map(p => [p[0], p[1]]);
    let validSegmentsWithIndices = extractValidSegmentsWithIndices(coordsOnly, {
        minPoints: Math.trunc(window.rawPath.length * 0.05)
    });

    if (!validSegmentsWithIndices.length) {
        const fallbackPoints = window.rawPath
            .map((p, idx) => ({
                coord: [p[0], p[1]],
                index: idx
            }))
            .filter(item => item.coord[0] !== 0 || item.coord[1] !== 0);

        if (fallbackPoints.length) {
            validSegmentsWithIndices = [fallbackPoints];
        }
    }

    const segmentsCoords = validSegmentsWithIndices.map(seg => seg.map(p => p.coord));
    const flatCoords = segmentsCoords.flat();
    const flatIndices = validSegmentsWithIndices.flat().map(p => p.index);

    window.MapData = {
        segmentsCoords,
        segmentsIndices: validSegmentsWithIndices,
        flatCoords,
        flatIndices
    };

    window.MapData.rawPathLength = window.rawPath.length;

    const origHeading = {};
    window.rawPath.forEach((point, idx) => {
        if (point.length >= 3) {
            origHeading[idx] = point[2];
        }
    });
    window.MapData.origHeading = origHeading;

    const origToFlat = {};
    window.MapData.segmentsIndices.flat().forEach(({ index }) => {
        if (index >= 0 && !(index in origToFlat)) {
            origToFlat[index] = window.MapData.flatIndices.indexOf(index);
        }
    });
    window.MapData.origToFlat = origToFlat;

    window.chartRangeStart = 0;
    window.chartRangeEnd = 0;

    initMap = initMapLeaflet;

    const checkTranslationsCache = () => {
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.startsWith('translations-cache-')) {
                return true;
            }
        }
        return false;
    };

    const initMapLogic = () => {
        jsCBinitMap = () => $(document).ready(initMap);
        jsCBinitMap();
    };

    const intervalId = setInterval(() => {
        if (checkTranslationsCache()) {
            clearInterval(intervalId);
            initMapLogic();
        }
    }, 100);
}
