"use strict";

/**
 * author Michal Zimmermann <zimmicz@gmail.com>
 * Displays coordinates of mouseclick.
 * @param object options:
 *        position: bottomleft, bottomright etc. (just as you are used to it with Leaflet)
 *        latitudeText: description of latitude value (defaults to lat.)
 *        longitudeText: description of latitude value (defaults to lon.)
 *        promptText: text displayed when user clicks the control
 *        precision: number of decimals to be displayed
 */
L.Control.Coordinates = L.Control.extend({
    options: {
	position: 'bottomleft',
	latitudeText: 'lat',
	longitudeText: 'lon',
	promptText: 'Press Ctrl+C to copy coordinates',
	precision: 4
    },

    initialize: function(options)
    {
	L.Control.prototype.initialize.call(this, options);
	this._hideTimer = null;
    },

    onAdd: function(map)
    {
	var className = 'leaflet-control-coordinates',
	    that = this,
	    container = this._container = L.DomUtil.create('div', className);
	this.visible = false;

	    L.DomUtil.addClass(container, 'hidden');


	L.DomEvent.disableClickPropagation(container);

	this._addText(container, map);

	L.DomEvent.addListener(container, 'click', function() {
	    var lat = L.DomUtil.get(that._lat),
		lng = L.DomUtil.get(that._lng),
		latTextLen = this.options.latitudeText.length + 1,
		lngTextLen = this.options.longitudeText.length + 1,
		latTextIndex = lat.textContent.indexOf(this.options.latitudeText) + latTextLen,
		lngTextIndex = lng.textContent.indexOf(this.options.longitudeText) + lngTextLen,
		latCoordinate = lat.textContent.substr(latTextIndex),
		lngCoordinate = lng.textContent.substr(lngTextIndex);

//	    window.prompt(this.options.promptText, latCoordinate + ' ' + lngCoordinate);
	    var coords;
		coords = latCoordinate + lngCoordinate;
		coords = coords.substring(1);
		const textArea = document.createElement("textarea");
		textArea.value = coords;
		document.body.appendChild(textArea);
		textArea.select();
		document.execCommand('copy');
		document.body.removeChild(textArea);
	    var dialogOpt = {
		title : localization.key['dialog.coord.title'],
		btnClassSuccessText: "OK",
		btnClassFail: "hidden",
		message : localization.key['dialog.coord.msg'],
		onResolve: function(){return;}
		};
	    redDialog.make(dialogOpt);
	}, this);

	if (that._hideTimer) {
		clearTimeout(that._hideTimer);
		that._hideTimer = null;
	}

	return container;
    },

    _addText: function(container, context)
    {
	this._lat = L.DomUtil.create('span', 'leaflet-control-coordinates-lat' , container),
	this._lng = L.DomUtil.create('span', 'leaflet-control-coordinates-lng' , container);

	return container;
    },

    /**
     * This method should be called when user clicks the map.
     * @param event object
     */
    setCoordinates: function(obj) {
        if (this._hideTimer) {
            clearTimeout(this._hideTimer);
            this._hideTimer = null;
        }

        if (!this.visible) {
            L.DomUtil.removeClass(this._container, 'hidden');
            this.visible = true;
        }

        this._hideTimer = setTimeout(() => {
            L.DomUtil.addClass(this._container, 'hidden');
            this.visible = false;
            this._hideTimer = null;
        }, 5000);

        if (obj.latlng) {
            // Нормализация долготы
            var normalizedLng = this.normalizeLongitude(obj.latlng.lng);

            L.DomUtil.get(this._lat).innerHTML = '<strong>' + this.options.latitudeText + ':</strong> ' + obj.latlng.lat.toFixed(this.options.precision).toString();
            L.DomUtil.get(this._lng).innerHTML = '<strong>' + this.options.longitudeText + ':</strong> ' + normalizedLng.toFixed(this.options.precision).toString();
        }
    },

    /**
     * long normalization in range [-180, 180]
     * @param {number} lng - long
     * @returns {number} Normalized long
     */
    normalizeLongitude: function(lng) {
        while (lng > 180) {
            lng -= 360;
        }
        while (lng < -180) {
            lng += 360;
        }
        return lng;
    }
});