# Changelog

All notable changes to `symfony-aggregated-queries` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-12-16

### Added
- Initial release
- Support for MySQL 8.0+ and PostgreSQL 12.0+
- `withJsonRelation()` for ManyToOne/OneToOne relations
- `withJsonCollection()` for OneToMany relations
- `withCount()` for relation counting
- `AggregatedRepositoryTrait` for easy integration
- Basic query filters (where, whereIn, orderBy, limit, offset)
- Array and Entity hydrators
- Cross-database JSON aggregation support
- Comprehensive test suite (unit + functional)
- PHPStan level 9 compliance

### Performance
- Up to 85.9% faster than traditional Doctrine queries
- 92.2% less memory usage
- Reduces N+1 queries to single optimized SQL statement

### Documentation
- Complete README with examples
- 5 working examples in `/examples` directory
- API reference documentation
- Troubleshooting guide

[1.0.0]: https://github.com/rgalstyan/symfony-aggregated-queries/releases/tag/v1.0.0