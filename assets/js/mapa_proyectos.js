document.addEventListener('DOMContentLoaded', function () {
    const map = L.map('mapid').setView([-1.8312, -78.1834], 7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    const geoJsonUrl = 'https://raw.githubusercontent.com/pabl-o-ce/Ecuador-geoJSON/master/geojson/provinces.geojson';
    const projectsApiUrl = 'api_get_proyectos_por_provincia.php';

    let geojsonLayer;

    // Función para obtener el color basado en el número de proyectos
    function getColor(projectCount) {
        return projectCount > 100 ? '#800026' :
               projectCount > 50  ? '#BD0026' :
               projectCount > 20  ? '#E31A1C' :
               projectCount > 10  ? '#FC4E2A' :
               projectCount > 5   ? '#FD8D3C' :
               projectCount > 0   ? '#FEB24C' :
                                  '#FFEDA0';
    }

    // Cargar los datos de proyectos y el GeoJSON
    Promise.all([fetch(projectsApiUrl), fetch(geoJsonUrl)])
        .then(responses => Promise.all(responses.map(res => res.json())))
        .then(([projectData, geojsonData]) => {
            if (!projectData.ok) {
                throw new Error(projectData.message || 'Error cargando datos de proyectos');
            }

            const projectsByProvince = {};
            projectData.provincias.forEach(p => {
                projectsByProvince[p.provincia.toUpperCase()] = {
                    total_proyectos: parseInt(p.total_proyectos, 10),
                    num_asignaciones: parseInt(p.num_asignaciones, 10)
                };
            });

            geojsonLayer = L.geoJSON(geojsonData, {
                style: feature => {
                    const provinceName = feature.properties.province.toUpperCase();
                    const data = projectsByProvince[provinceName];
                    const projectCount = data ? data.total_proyectos : 0;
                    return {
                        fillColor: getColor(projectCount),
                        weight: 2,
                        opacity: 1,
                        color: 'white',
                        dashArray: '3',
                        fillOpacity: 0.7
                    };
                },
                onEachFeature: (feature, layer) => {
                    const provinceName = feature.properties.province.toUpperCase();
                    const data = projectsByProvince[provinceName];
                    const projectCount = data ? data.total_proyectos : 0;
                    const assignmentsCount = data ? data.num_asignaciones : 0;

                    let popupContent = `<b>${feature.properties.province}</b><br/>Total Proyectos: ${projectCount}<br/>Asignaciones: ${assignmentsCount}`;
                    layer.bindPopup(popupContent);

                    layer.on({
                        mouseover: e => {
                            const l = e.target;
                            l.setStyle({ weight: 5, color: '#666', dashArray: '' });
                            if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                                l.bringToFront();
                            }
                        },
                        mouseout: e => {
                            geojsonLayer.resetStyle(e.target);
                        },
                        click: e => {
                            map.fitBounds(e.target.getBounds());
                        }
                    });
                }
            }).addTo(map);

            addLegend();
        })
        .catch(error => {
            console.error('Error al cargar los datos:', error);
            alert('No se pudieron cargar los datos para el mapa. Verifique la consola para más detalles.');
        });

    // Añadir leyenda
    function addLegend() {
        const legend = L.control({ position: 'bottomright' });
        legend.onAdd = function (map) {
            const div = L.DomUtil.create('div', 'info legend');
            const grades = [0, 5, 10, 20, 50, 100];
            div.innerHTML += '<strong>Proyectos</strong><br>';
            for (let i = 0; i < grades.length; i++) {
                div.innerHTML += '<i style="background:' + getColor(grades[i] + 1) + '"></i> ' +
                                 grades[i] + (grades[i + 1] ? '&ndash;' + grades[i + 1] + '<br>' : '+');
            }
            return div;
        };
        legend.addTo(map);
    }

    // Lógica de filtros
    const filtroZona = document.getElementById('filtro_zona');
    const filtroBloque = document.getElementById('filtro_bloque');

    filtroZona.addEventListener('change', function() {
        const zona = this.value;
        filtroBloque.value = ''; // Reset other filter
        if (zona) {
            zoomToFilter({ zona: zona });
        } else {
            map.setView([-1.8312, -78.1834], 7);
        }
    });

    filtroBloque.addEventListener('change', function() {
        const bloque = this.value;
        filtroZona.value = ''; // Reset other filter
        if (bloque) {
            zoomToFilter({ bloque: bloque });
        } else {
            map.setView([-1.8312, -78.1834], 7);
        }
    });

    function zoomToFilter(filter) {
        let url = 'api_get_bloque_details.php?';
        if (filter.zona) {
            url += `zona=${encodeURIComponent(filter.zona)}`;
        } else if (filter.bloque) {
            url += `bloque=${encodeURIComponent(filter.bloque)}`;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.ok && data.provincias.length > 0) {
                    const targetProvinces = data.provincias.map(p => p.toUpperCase());
                    let bounds = L.latLngBounds();
                    let found = false;

                    geojsonLayer.eachLayer(layer => {
                        const provinceName = layer.feature.properties.province.toUpperCase();
                        if (targetProvinces.includes(provinceName)) {
                            bounds.extend(layer.getBounds());
                            found = true;
                        }
                    });

                    if (found) {
                        map.fitBounds(bounds);
                    } else {
                        alert('No se encontraron geometrías para la selección.');
                    }
                } else {
                    alert('No se encontraron provincias para la selección.');
                }
            })
            .catch(error => {
                console.error('Error al filtrar:', error);
                alert('Error al intentar aplicar el filtro.');
            });
    }
});