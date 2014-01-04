<?php
namespace Craft;

/**
 * Craft by Pixel & Tonic
 *
 * @package   Craft
 * @author    Pixel & Tonic, Inc.
 * @copyright Copyright (c) 2013, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @link      http://buildwithcraft.com
 */

/**
 * Internal node used by the nav node.
 */
class NavItem_Node extends \Twig_Node
{
	public function __construct(\Twig_Node_Expression_AssignName $valueTarget, \Twig_NodeInterface $indent = null, \Twig_NodeInterface $outdent = null, \Twig_NodeInterface $lowerBody = null, $lineno, $tag = null)
	{
		parent::__construct(array('value_target' => $valueTarget, 'indent' => $indent, 'outdent' => $outdent, 'lower_body' => $lowerBody), array(), $lineno, $tag);
	}

	/**
	 * Compiles the node to PHP.
	 *
	 * @param \Twig_Compiler $compiler
	 */
	public function compile(\Twig_Compiler $compiler)
	{
		$compiler
			// Get this item's depth
			->write('$_thisItemDepth = (int)$this->getAttribute(')
			->subcompile($this->getNode('value_target'))
			->raw(", 'depth', array(), Twig_TemplateInterface::ANY_CALL, false, true);\n")
			// Was there a previous item?
			->write("if (isset(\$context['nav'])) {\n")
			->indent()
				// Temporarily set the context to the previous one
				->write("\$_tmpContext = \$context;\n")
				->write("\$context = \$_contextsByDepth[\$context['nav']['depth']];\n")
				// Does this one have a greater depth than the last one?
				->write("if (\$_thisItemDepth > \$context['nav']['depth']) {\n")
				->indent()
					->write("for (\$_i = \$context['nav']['depth']; \$_i < \$_thisItemDepth; \$_i++) {\n")
					->indent()
						->subcompile($this->getNode('indent'), false)
					->outdent()
					->write("}\n")
				->outdent()
				->write("} else {\n")
				->indent()
					->subcompile($this->getNode('lower_body'), false)
					// Does this one have a lower depth than the last one?
					->write("if (\$_thisItemDepth < \$context['nav']['depth']) {\n")
					->indent()
						->write("for (\$_i = \$context['nav']['depth']-1; \$_i >= \$_thisItemDepth; \$_i--) {\n")
						->indent()
							// Did we output an item at that depth?
							->write("if (isset(\$_contextsByDepth[\$_i])) {\n")
							->indent()
								// Temporarily set the context to the element at this depth
								->write("\$context = \$_contextsByDepth[\$_i];\n")
								->subcompile($this->getNode('outdent'), false)
								->subcompile($this->getNode('lower_body'), false)
								->write("unset(\$_contextsByDepth[\$_i]);\n")
							->outdent()
							->write("}\n")
						->outdent()
						->write("}\n")
					->outdent()
					->write("}\n")
				->outdent()
				->write("}\n")
				// Set the context back
				->write("\$context = \$_tmpContext;\n")
				->write("unset(\$_tmpContext);\n")
			->outdent()
			->write("} else {\n")
			->indent()
				// This is the first item so save its depth
				->write("\$_firstItemDepth = \$_thisItemDepth;\n")
			->outdent()
			->write("}\n")
			// Create the nav array for this item
			->write("\$context['nav']['depth'] = \$_thisItemDepth;\n")
			->write("if (isset(\$_contextsByDepth[\$_thisItemDepth-1])) {\n")
			->indent()
				->write("\$context['nav']['parent'] = \$_contextsByDepth[\$_thisItemDepth-1];\n")
				// Might as well set the item's parent so long as we have it
				->write('if (method_exists(')
				->subcompile($this->getNode('value_target'))
				->raw(", 'setParent')) {\n")
				->indent()
					->subcompile($this->getNode('value_target'), false)
					->raw("->setParent(\$context['nav']['parent'][")
					->string($this->getNode('value_target')->getAttribute('name'))
					->raw("]);\n")
				->outdent()
				->write("}\n")
			->outdent()
			->write("} else {\n")
			->indent()
				->write("\$context['nav']['parent'] = null;\n")
			->outdent()
			->write("}\n")
			// Save a reference of this item for the next iteration
			->write("\$_contextsByDepth[\$_thisItemDepth] = \$context;\n")
		;
	}
}
