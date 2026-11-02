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
            height: calc(100vh - 60px);
        }

        #viewDiv {
            width: 100%;
            height: 100vh;
        }

        #layer-panel {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 10;
            width: 260px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .layer-header {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            font-size: 15px;
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
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 18px;
            width: 25px;
        }

        .layer-name {
            font-size: 13px;
            flex: 1;
        }

        #viewDiv {
            width: 100%;
            height: 100vh;
            min-height: 500px;
        }
    </style>
</head>

<body>

    <div class="header">
        <h3>WebGIS Irigasi - ArcGIS Online</h3>
    </div>
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

            const graphicsLayer = new GraphicsLayer({
                title: "Areal Kerja PT SMD",
                visible: true
            });

            const map = new Map({
                basemap: "topo-vector",
                layers: [graphicsLayer]
            });

            const view = new MapView({
                container: "viewDiv",
                map: map,
                center: [112.3121, 0.6183],
                zoom: 12
            });

            // Ambil data dari Laravel
            fetch('/arcgis/features')
                .then(response => {
                    if (!response.ok) {
                        throw new Error("HTTP Error: " + response.status);
                    }
                    return response.json();
                })
                .then(data => {

                    console.log("Data ArcGIS:", data);

                    if (!data.features || data.features.length === 0) {
                        console.warn("Tidak ada data shape.");
                        return;
                    }

                    data.features.forEach(feature => {

                        if (!feature.geometry || !feature.geometry.rings) {
                            console.warn("Geometry tidak ditemukan:", feature);
                            return;
                        }

                        const polygon = new Polygon({
                            rings: feature.geometry.rings,
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
                                title: "Areal Kerja PT SMD",
                                content: "Luas: {luas}"
                            }
                        });

                        graphicsLayer.add(graphic);
                    });

                    // Arahkan peta ke lokasi shape
                    if (graphicsLayer.graphics.length > 0) {
                        view.goTo(graphicsLayer.graphics).catch(error => {
                            console.error("Gagal mengarahkan peta:", error);
                        });
                    }
                })
                .catch(error => {
                    console.error("Gagal memuat shape:", error);
                });

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
