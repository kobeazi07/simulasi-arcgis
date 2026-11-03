<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>WebGIS Irigasi - ArcGIS Online</title>

    <link rel="stylesheet" href="https://js.arcgis.com/4.32/esri/themes/light/main.css">

    <script src="https://js.arcgis.com/4.32/"></script>

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            font-family: Arial, sans-serif;
        }

        .header {
            height: 60px;
            background: #1f2937;
            color: white;
            display: flex;
            align-items: center;
            padding: 0 20px;
        }

        #viewDiv {
            width: 100%;
            height: 100vh;
        }

        #layer-panel {
            position: absolute;
            top: 75px;
            right: 20px;
            z-index: 100;
            width: 260px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .layer-header {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            font-size: 15px;
            font-weight: bold;
        }

        .layer-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .layer-item:hover {
            background: #f5f5f5;
        }

        .layer-eye {
            width: 30px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 18px;
        }

        .layer-name {
            flex: 1;
            font-size: 13px;
        }
    </style>
</head>

<body>
    <div id="layer-panel">
        <div class="layer-header">
            <strong>Daftar Layer</strong>
        </div>

        <div id="layer-list"></div>
    </div>


    <div id="viewDiv"></div>
    <script>
        require([
            "esri/Map",
            "esri/views/MapView",
            "esri/layers/GraphicsLayer",
            "esri/Graphic",
            "esri/geometry/Polygon"
        ], function(Map, MapView, GraphicsLayer, Graphic, Polygon) {

            const map = new Map({
                basemap: "topo-vector"
            });

            const view = new MapView({
                container: "viewDiv",
                map: map,
                center: [112.3121, 0.6183],
                zoom: 12
            });

            const layerObjects = [];

            fetch('/arcgis/features')
                .then(response => response.json())
                .then(data => {

                    if (!data.features || data.features.length === 0) {
                        console.warn("Data shape tidak ditemukan.");
                        return;
                    }

                    let shapeNumber = 0;

                    data.features.forEach((feature, featureIndex) => {

                        const rings = feature.geometry?.rings ?? [];

                        // Setiap ring dibuat sebagai shape terpisah.
                        rings.forEach((ring, ringIndex) => {

                            shapeNumber++;

                            const graphicsLayer = new GraphicsLayer({
                                title: `Shape ${shapeNumber}`,
                                visible: true
                            });

                            const polygon = new Polygon({
                                rings: [ring],
                                spatialReference: {
                                    wkid: 4326
                                }
                            });

                            const graphic = new Graphic({
                                geometry: polygon,
                                attributes: feature.attributes,
                                symbol: {
                                    type: "simple-fill",
                                    color: [0, 150, 100, 0.35],
                                    outline: {
                                        color: [0, 100, 70, 1],
                                        width: 2
                                    }
                                },
                                popupTemplate: {
                                    title: `Shape ${shapeNumber}`,
                                    content: `Luas: ${feature.attributes.luas ?? '-'}`
                                }
                            });

                            graphicsLayer.add(graphic);
                            map.add(graphicsLayer);

                            layerObjects.push({
                                id: shapeNumber,
                                name: `Shape ${shapeNumber}`,
                                layer: graphicsLayer,
                                visible: true
                            });

                        });
                    });

                    renderLayerPanel();

                    // Zoom ke seluruh shape
                    const allGraphics = layerObjects.flatMap(item =>
                        item.layer.graphics.toArray()
                    );

                    if (allGraphics.length > 0) {
                        view.goTo(allGraphics);
                    }

                })
                .catch(error => {
                    console.error("Gagal memuat shape:", error);
                });


            // Membuat daftar layer
            function renderLayerPanel() {

                const layerList = document.getElementById('layer-list');

                layerList.innerHTML = '';

                layerObjects.forEach(item => {

                    const row = document.createElement('div');
                    row.className = 'layer-item';

                    const button = document.createElement('button');
                    button.className = 'layer-eye';

                    button.innerHTML = item.visible ? '👁️' : '🚫';
                    button.title = item.visible ?
                        'Sembunyikan shape' :
                        'Tampilkan shape';

                    const name = document.createElement('span');
                    name.className = 'layer-name';
                    name.textContent = item.name;

                    button.addEventListener('click', function() {

                        item.visible = !item.visible;

                        item.layer.visible = item.visible;

                        button.innerHTML = item.visible ? '👁️' : '🚫';

                        button.title = item.visible ?
                            'Sembunyikan shape' :
                            'Tampilkan shape';

                    });

                    row.appendChild(button);
                    row.appendChild(name);

                    layerList.appendChild(row);

                });
            }

        });
    </script>
    {{-- <script>
        require([
            "esri/Map",
            "esri/views/MapView",
            "esri/layers/TileLayer",
            "esri/layers/FeatureLayer"
        ], function(Map, MapView, TileLayer, FeatureLayer) {

            // Basemap ArcGIS Online
            const basemap = new TileLayer({
                url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer"
            });

            const map = new Map({
                layers: [basemap]
            });

            // Inisialisasi peta
            const view = new MapView({
                container: "viewDiv",
                map: map,
                center: [112.35, 0.60],
                zoom: 10
            });

            view.when(function() {
                console.log("Peta berhasil dimuat!");
            }).catch(function(error) {
                console.error("Map error:", error);
            });

            // Feature Layer ArcGIS Online
            // const featureLayer = new FeatureLayer({
            //     url: "https://services8.arcgis.com/YLHmdINbGz9kn9v4/arcgis/rest/services/areal_kerja_PT_SMD/FeatureServer/0",
            //     title: "Areal Kerja PT SMD",
            //     outFields: ["*"],

            //     popupTemplate: {
            //         title: "Areal Kerja PT SMD",
            //         content: [{
            //             type: "fields",
            //             fieldInfos: [{
            //                     fieldName: "FID",
            //                     label: "FID"
            //                 },
            //                 {
            //                     fieldName: "Id",
            //                     label: "ID"
            //                 },
            //                 {
            //                     fieldName: "luas",
            //                     label: "Luas"
            //                 }
            //             ]
            //         }]
            //     }
            // });

            // map.add(featureLayer);
            const featureLayer = new FeatureLayer({
                url: "https://services8.arcgis.com/YLHmdINbGz9kn9v4/arcgis/rest/services/areal_kerja_PT_SMD/FeatureServer/0"
            });

            map.add(featureLayer);

            featureLayer.when(function() {
                console.log("Feature Layer berhasil dimuat!");

                return featureLayer.queryExtent();

            }).then(function(result) {

                if (result && result.extent) {
                    view.goTo(result.extent.expand(1.3));
                }

            }).catch(function(error) {
                console.error("Feature Layer error:", error);
            });

        });
    </script> --}}

</body>

</html>
