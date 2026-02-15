# ADR-021: ML Movie Recommender (PHP-ML)

**Fecha**: 2026-02-15  
**Estado**: Aceptado  
**Autor**: Martín Pallante

## Contexto

El proyecto necesita demostrar capacidades de Machine Learning aplicadas al dominio Marvel.
Se requiere una implementación ML real integrada en la arquitectura existente, 
que funcione en el hosting compartido (Hostinger) sin dependencias de Python ni GPU.

## Decisión

Implementar un **recomendador de películas Marvel** usando **PHP-ML** con:

- **KNN (K-Nearest Neighbors)** con distancia Euclidiana para features numéricos
- **Jaccard Similarity** para comparación textual de sinopsis
- **Feature extraction**: vote_average, release_year, overview_length, overview_text
- **Pesos**: 60% features numéricos, 40% similitud textual

### Arquitectura

```
Domain:         MovieRecommenderInterface (contrato)
Application:    RecommendMoviesUseCase (orquestación)
Infrastructure: PhpMlMovieRecommender (implementación ML)
```

### Features del modelo

| Feature | Tipo | Normalización |
|---------|------|---------------|
| vote_average | Numérico | 0-1 (dividido por 10) |
| release_year | Numérico | 0-1 (2008-2030 range) |
| overview_length | Numérico | 0-1 (max 500 chars) |
| overview_words | Texto | Jaccard similarity con stop words |

## Alternativas consideradas

| Opción | Motivo de rechazo |
|--------|-------------------|
| Python + scikit-learn | No soportado en Hostinger (hosting PHP only) |
| API ML externa (AWS/GCP) | Coste y dependencia externa innecesaria |
| Simple sort by vote | No es ML real, no demuestra conocimiento |
| TensorFlow.js frontend | Over-engineering para el caso de uso |

## Consecuencias

**Positivas:**
- ML real integrado en Clean Architecture
- Compatible con hosting compartido PHP
- Tests unitarios verifican precisión del modelo
- Endpoint REST documentado con metadata del algoritmo
- Zero dependencias externas en runtime (datos vienen de TMDB)

**Negativas:**
- PHP-ML tiene menor ecosistema que scikit-learn
- Modelo no se re-entrena (features fijas), pero es suficiente para recomendación por similitud
