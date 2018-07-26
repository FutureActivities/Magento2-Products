# FutureActivities Products

Provides additional Magento 2 REST API endpoints for:

1. Get products where parents are returned instead of the child product
2. Get layered navigation for available products


## Get products list - Returns the parent instead of child 

By default, the Magento API call for `/rest/V1/products` returns child products,
but in some cases it may be useful to return the parent product instead of the child.

    GET /rest/V1/fa/products
    
With the following params:

    searchCriteria - the same as any other Magento searchCriteria

If the there are multiple children the parent will only be returned once, positioned
where the first child product was positioned.

## Get product filters

Returns a list of attribute or category values being used by a collection of products.

    GET /rest/V1/fa/filter
    
With the following params:

    searchCriteria - the same as any other Magento searchCriteria
    
You must also set the filterable fields JSON in the Magento configuration, example:

    [
        {
    		"handle": "category",
    		"name": "Category",
    		"type": "category",
    		"id": "6",
            "path":"2"
    	},
    	{
    		"handle": "subcategory",
    		"name": "Sub Category",
    		"type": "category",
    		"parent": "category"
    	},
    	{
    		"handle": "color",
    		"name": "Color",
    		"type": "attribute",
    		"id": "color"
    	},
    	{
    		"handle": "size",
    		"name": "Size",
    		"type": "attribute",
    		"id": "size"
    	}
    ]
    
`parent` is available on categories only and will only show in the filter once
a value for the parent filter has been selected.

### Filtering Behaviour

By default, the filter will only show attribute values applicable to the collection of products
found with the search criteria.

However, if you set the behaviour to 'Multiple' in the admin configuration, then the attribute that 
matches the last filter will be filtered based on a collection of products with the last filter group 
removed. This will allow you to select multiple options for the last selected attribute.

## Changelog

## v1.1

- Added an option to adjust how the filtering works
- Added product counts