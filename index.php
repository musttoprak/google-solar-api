<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Solar API Harita Entegrasyonu</title>
    <style>
        #map { height: 400px; width: 100%; }
        #debug { margin-top: 20px; padding: 10px; background-color: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Google Solar API Harita Entegrasyonu</h1>
    <div id="map"></div>
    <div id="info"></div>
    <div id="debug"></div>

    <script>
        let map;
        const apiKey = 'YOUR_API_KEY';

        function initMap() {
            map = new google.maps.Map(document.getElementById('map'), {
                center: {lat: 41.092865156416345, lng: 28.991783817617385},
                zoom: 18
            });

            map.addListener('click', function(e) {
                fetchSolarData(e.latLng.lat(), e.latLng.lng());
            });
        }

        function fetchSolarData(lat, lng) {
            const url = `https://solar.googleapis.com/v1/buildingInsights:findClosest?location.latitude=${lat}&location.longitude=${lng}&requiredQuality=HIGH&key=${apiKey}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    displaySolarData(data, lat, lng);
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('info').innerHTML = 'Veri alınırken bir hata oluştu.';
                    debugLog('Fetch error: ' + error.message);
                });
        }

        function displaySolarData(data, lat, lng) {
            debugLog('Received data: ' + JSON.stringify(data, null, 2));

            if (data.error) {
                document.getElementById('info').innerHTML = `Hata: ${data.error.message}`;
                debugLog('API error: ' + data.error.message);
                return;
            }

            const solarPotential = data.solarPotential || {};
            const roofSegmentStats = solarPotential.roofSegmentStats || [];

            // Çatı poligonlarını çizme
            if (roofSegmentStats.length > 0) {
                roofSegmentStats.forEach((segment, index) => {
                    const color = getColorForSegment(segment.pitchDegrees);
                    let paths;
                    
                    try {
                        if (segment.boundingBox) {
                            const { sw, ne } = segment.boundingBox;
                            if (!isValidLatLng(sw.latitude, sw.longitude) ||
                                !isValidLatLng(ne.latitude, ne.longitude)) {
                                debugLog(`Invalid LatLng in boundingBox of segment ${index}: ${JSON.stringify(segment.boundingBox)}`);
                                throw new Error('Invalid LatLng in boundingBox');
                            }
                            paths = [
                                {lat: sw.latitude, lng: sw.longitude},
                                {lat: sw.latitude, lng: ne.longitude},
                                {lat: ne.latitude, lng: ne.longitude},
                                {lat: ne.latitude, lng: sw.longitude}
                            ];
                        } else {
                            debugLog(`No valid polygon data in segment ${index}: ${JSON.stringify(segment)}`);
                            throw new Error('No valid polygon data');
                        }

                        const polygon = new google.maps.Polygon({
                            paths: paths,
                            strokeColor: color,
                            strokeOpacity: 0.8,
                            strokeWeight: 2,
                            fillColor: color,
                            fillOpacity: 0.35,
                            map: map
                        });

                        const infoWindow = new google.maps.InfoWindow({
                            content: `Segment ${index + 1}<br>Eğim: ${(segment.pitchDegrees || 0).toFixed(2)}°<br>Alan: ${(segment.stats.areaMeters2 || 0).toFixed(2)} m²`
                        });

                        polygon.addListener('click', function(event) {
                            infoWindow.setPosition(event.latLng);
                            infoWindow.open(map);
                        });
                    } catch (error) {
                        console.error('Error creating polygon for segment', index, ':', error);
                        debugLog(`Error in segment ${index}: ${error.message}`);
                    }
                });
            } else {
                debugLog('No roofSegmentStats found in the data');
            }

            // Genel bilgi penceresi
            const marker = new google.maps.Marker({
                position: {lat: lat, lng: lng},
                map: map,
                title: 'Solar Potansiyeli'
            });

            const infoContent = `
                <h3>Solar Potansiyel Bilgileri</h3>
                <p>Maksimum Güneş Paneli Kapasitesi: ${solarPotential.maxArrayPanelsCount || 'Veri yok'}</p>
                <p>Maksimum Güneş Enerjisi Kapasitesi: ${(solarPotential.maxArrayAreaMeters2 || 0).toFixed(2)} m²</p>
                <p>Yıllık Enerji Üretimi: ${(solarPotential.maxSunshineHoursPerYear || 0).toFixed(2)} saat</p>
            `;

            const infowindow = new google.maps.InfoWindow({
                content: infoContent
            });

            marker.addListener('click', function() {
                infowindow.open(map, marker);
            });

            document.getElementById('info').innerHTML = infoContent;
        }

        function getColorForSegment(pitch) {
            pitch = pitch || 0;
            if (pitch < 15) return '#00FF00'; // Yeşil
            if (pitch < 30) return '#FFFF00'; // Sarı
            if (pitch < 45) return '#FFA500'; // Turuncu
            return '#FF0000'; // Kırmızı
        }

        function isValidLatLng(lat, lng) {
            return !isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
        }

        function debugLog(message) {
            const debugElement = document.getElementById('debug');
            debugElement.innerHTML = message + '<br>';
            console.log(message);
        }

        // Google Maps API'sini yükleme
        function loadGoogleMapsAPI() {
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&callback=initMap`;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }

        window.initMap = initMap;
        window.onload = loadGoogleMapsAPI;
    </script>
</body>
</html>
